<?php

use App\Models\Payment;
use App\Models\User;
use App\Services\Billing\MidtransService;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    config([
        'midtrans.server_key' => 'SB-server-key',
        'midtrans.client_key' => 'SB-client-key',
        'midtrans.is_production' => false,
        'plans.pro_price' => 29000,
        'plans.pro_days' => 30,
    ]);
});

/** Signed notification body, the way Midtrans sends it. */
function midtransBody(Payment $payment, array $override = []): array
{
    $body = $override + [
        'order_id' => $payment->order_id,
        'status_code' => '200',
        'gross_amount' => '29000.00',
        'transaction_status' => 'settlement',
    ];
    $body['signature_key'] ??= hash('sha512', $body['order_id'].$body['status_code'].$body['gross_amount'].'SB-server-key');

    return $body;
}

/** What the Midtrans status API answers for an order (the authoritative re-check). */
function midtransApiFake(string $status = 'settlement', array $extra = []): void
{
    // Fresh factory per call: stubs registered earlier would otherwise keep winning.
    Http::swap(new Factory);
    Http::fake([
        'api.sandbox.midtrans.com/*' => Http::response($extra + [
            'transaction_status' => $status,
            'gross_amount' => '29000.00',
            'payment_type' => 'qris',
            'transaction_id' => 'trx-1',
        ]),
    ]);
}

test('a notification with a wrong signature is rejected and nothing is changed or fetched', function () {
    Http::fake();
    $payment = Payment::factory()->create();

    $this->postJson(route('midtrans.notification'), midtransBody($payment, ['signature_key' => 'nope']))->assertForbidden();

    expect($payment->fresh()->status)->toBe('pending')
        ->and($payment->user->fresh()->pro_until)->toBeNull();
    Http::assertNothingSent();
});

test('malformed notification fields (array, object, null, missing) are rejected with 403 and nothing is fetched', function (string $field, mixed $value) {
    Http::fake();
    $payment = Payment::factory()->create();
    $body = midtransBody($payment);
    $value === '__missing__' ? $body = array_diff_key($body, [$field => 1]) : $body[$field] = $value;

    $this->postJson(route('midtrans.notification'), $body)->assertForbidden();

    expect($payment->fresh()->status)->toBe('pending')->and($payment->user->fresh()->pro_until)->toBeNull();
    Http::assertNothingSent();
})->with([
    'order_id array' => ['order_id', ['x']],
    'order_id object' => ['order_id', ['a' => 1]],
    'status_code null' => ['status_code', null],
    'gross_amount array' => ['gross_amount', [1, 2]],
    'signature_key array' => ['signature_key', ['x']],
    'signature_key missing' => ['signature_key', '__missing__'],
    'order_id missing' => ['order_id', '__missing__'],
]);

test('settlement activates Pro for 30 days, without a session or CSRF token', function () {
    midtransApiFake();
    $payment = Payment::factory()->create();

    $this->postJson(route('midtrans.notification'), midtransBody($payment))->assertOk()->assertJson(['status' => 'settlement']);

    $payment->refresh();
    expect($payment->isPaid())->toBeTrue()
        ->and($payment->payment_type)->toBe('qris')
        ->and($payment->transaction_id)->toBe('trx-1')
        ->and($payment->user->fresh()->pro_until->isSameDay(now()->addDays(30)))->toBeTrue();
});

test('a repeated notification does not extend Pro twice', function () {
    midtransApiFake();
    $payment = Payment::factory()->create();

    $this->postJson(route('midtrans.notification'), midtransBody($payment))->assertOk();
    $until = $payment->user->fresh()->pro_until;
    $this->postJson(route('midtrans.notification'), midtransBody($payment))->assertOk();

    expect($payment->user->fresh()->pro_until->equalTo($until))->toBeTrue();
});

test('paying while Pro is still active adds 30 days on top of the remaining time', function () {
    midtransApiFake();
    $user = User::factory()->pro()->create();
    $before = $user->pro_until->copy();
    $payment = Payment::factory()->for($user)->create();

    $this->postJson(route('midtrans.notification'), midtransBody($payment))->assertOk();

    expect($user->fresh()->pro_until->isSameDay($before->addDays(30)))->toBeTrue();
});

test('capture with fraud accept activates Pro, fraud challenge does not', function () {
    midtransApiFake('capture', ['fraud_status' => 'challenge']);
    $payment = Payment::factory()->create();
    $this->postJson(route('midtrans.notification'), midtransBody($payment))->assertOk();
    expect($payment->fresh()->isPaid())->toBeFalse()->and($payment->user->fresh()->pro_until)->toBeNull();

    midtransApiFake('capture', ['fraud_status' => 'accept']);
    $this->postJson(route('midtrans.notification'), midtransBody($payment))->assertOk();
    expect($payment->fresh()->isPaid())->toBeTrue();
});

test('expire, cancel and deny never change pro_until', function (string $status) {
    midtransApiFake($status);
    $payment = Payment::factory()->create();

    $this->postJson(route('midtrans.notification'), midtransBody($payment, ['transaction_status' => $status]))->assertOk();

    expect($payment->fresh()->status)->toBe($status)
        ->and($payment->fresh()->isPaid())->toBeFalse()
        ->and($payment->user->fresh()->pro_until)->toBeNull();
})->with(['expire', 'cancel', 'deny']);

test('the status comes from the API re-check, not from the notification body', function () {
    midtransApiFake('pending');
    $payment = Payment::factory()->create();

    $this->postJson(route('midtrans.notification'), midtransBody($payment, ['transaction_status' => 'settlement']))->assertOk();

    expect($payment->fresh()->isPaid())->toBeFalse()->and($payment->user->fresh()->pro_until)->toBeNull();
});

test('an API amount that differs from the order is ignored', function () {
    midtransApiFake('settlement', ['gross_amount' => '1000.00']);
    $payment = Payment::factory()->create();

    $this->postJson(route('midtrans.notification'), midtransBody($payment))->assertOk();

    expect($payment->fresh()->isPaid())->toBeFalse()->and($payment->user->fresh()->pro_until)->toBeNull();
});

test('a paid payment stays paid when a late expire arrives', function () {
    midtransApiFake();
    $payment = Payment::factory()->create();
    $this->postJson(route('midtrans.notification'), midtransBody($payment))->assertOk();

    midtransApiFake('expire');
    $this->postJson(route('midtrans.notification'), midtransBody($payment, ['transaction_status' => 'expire']))->assertOk();

    expect($payment->fresh()->isPaid())->toBeTrue()->and($payment->fresh()->status)->toBe('settlement');
});

test('when the status API is down Midtrans gets a 503 so it retries, and nothing changes', function () {
    Http::fake(['api.sandbox.midtrans.com/*' => Http::response('boom', 500)]);
    $payment = Payment::factory()->create();

    $this->postJson(route('midtrans.notification'), midtransBody($payment))->assertStatus(503);

    expect($payment->fresh()->isPaid())->toBeFalse();
});

test('an unknown order id answers 404', function () {
    Http::fake();
    $body = ['order_id' => 'LPK-unknown', 'status_code' => '200', 'gross_amount' => '29000.00'];
    $body['signature_key'] = hash('sha512', 'LPK-unknown'.'200'.'29000.00'.'SB-server-key');

    $this->postJson(route('midtrans.notification'), $body)->assertNotFound();
});

test('createSnapTransaction stores a pending payment with the Snap token', function () {
    Http::fake(['app.sandbox.midtrans.com/snap/v1/transactions' => Http::response(['token' => 'snap-token-1'])]);
    $user = User::factory()->create();

    $payment = app(MidtransService::class)->createSnapTransaction($user);

    expect($payment->status)->toBe('pending')
        ->and($payment->snap_token)->toBe('snap-token-1')
        ->and($payment->gross_amount)->toBe(29000)
        ->and($payment->order_id)->toStartWith('LPK-'.$user->id.'-');
    Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Basic '.base64_encode('SB-server-key:'))
        && $r['transaction_details']['gross_amount'] === 29000
        && $r['transaction_details']['order_id'] === $payment->order_id);
});

test('billing pages need a signed-in user', function () {
    $this->get(route('billing.upgrade'))->assertRedirect(route('login'));
    $this->get(route('settings.billing'))->assertRedirect(route('login'));
});

test('upgrade page shows plans and the order summary', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('billing.upgrade'))
        ->assertOk()
        ->assertSee('Rp29.000')
        ->assertSeeHtml('data-test="plan-pro"')
        ->assertSeeHtml('data-test="pay-pro"');
});

test('pay() creates a payment and opens Snap with its token', function () {
    Http::fake(['app.sandbox.midtrans.com/snap/v1/transactions' => Http::response(['token' => 'snap-token-2'])]);
    $user = User::factory()->create();

    Livewire::actingAs($user)->test('pages::billing.upgrade')
        ->call('pay')
        ->assertDispatched('snap-pay', token: 'snap-token-2');

    expect(Payment::where('user_id', $user->id)->count())->toBe(1);
});

test('pay() does nothing without Midtrans keys or for guest users', function () {
    Http::fake();

    config(['midtrans.server_key' => null]);
    $user = User::factory()->create();
    Livewire::actingAs($user)->test('pages::billing.upgrade')->call('pay')->assertNotDispatched('snap-pay');

    config(['midtrans.server_key' => 'SB-server-key']);
    $guest = User::factory()->guest()->create();
    Livewire::actingAs($guest)->test('pages::billing.upgrade')->call('pay')->assertNotDispatched('snap-pay');

    expect(Payment::count())->toBe(0);
    Http::assertNothingSent();
});

test('billing page lists only my payments', function () {
    $user = User::factory()->pro()->create();
    $mine = Payment::factory()->for($user)->create(['order_id' => 'LPK-MINE']);
    Payment::factory()->create(['order_id' => 'LPK-OTHER']);

    $this->actingAs($user)->get(route('settings.billing'))
        ->assertOk()
        ->assertSee('LPK-MINE')
        ->assertDontSee('LPK-OTHER')
        ->assertSeeHtml('data-test="resume-payment"');

    Livewire::actingAs(User::factory()->create())->test('pages::settings.billing')
        ->call('resume', $mine->id)
        ->assertStatus(404);
});
