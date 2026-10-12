<?php

use App\Models\User;
use App\Services\Ai\GenerationLimiter;
use App\Services\Billing\PlanLimits;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/** Clears the per-minute burst so the daily quota is what the test exercises. Builds the key like GenerationLimiter does. */
function clearMinuteBurst(): void
{
    $request = Request::create('/', 'POST', server: ['REMOTE_ADDR' => '127.0.0.1']);
    $request->setUserResolver(fn () => auth()->user());

    RateLimiter::clear(RateLimiter::limiter('ai-generate')($request)->key);
}

function spendOne(): bool
{
    clearMinuteBurst();

    return app(GenerationLimiter::class)->consume();
}

test('a free user gets five generations a day, then is refused', function () {
    $this->actingAs(User::factory()->create());

    $granted = collect(range(1, 6))->map(fn () => spendOne());

    expect($granted->filter()->count())->toBe(5)
        ->and($granted->last())->toBeFalse();
});

test('a pro user gets the larger daily allowance', function () {
    $this->actingAs(User::factory()->pro()->create());

    $granted = collect(range(1, 51))->map(fn () => spendOne());

    expect($granted->filter()->count())->toBe(50)
        ->and($granted->last())->toBeFalse();
});

test('generations from earlier days do not count toward today', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    DB::table('ai_generations')->insert(array_fill(0, 5, [
        'user_id' => $user->id,
        'kind' => 'generate',
        'created_at' => now()->subDay(),
    ]));

    expect(spendOne())->toBeTrue()
        ->and(app(PlanLimits::class)->generationsLeftToday($user))->toBe(4);
});

test('a multi-chapter write spends one unit per chapter and is refused as a whole when short', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    DB::table('ai_generations')->insert(array_fill(0, 3, [
        'user_id' => $user->id,
        'kind' => 'generate',
        'created_at' => now(),
    ]));

    clearMinuteBurst();
    expect(app(GenerationLimiter::class)->consume(3))->toBeFalse()
        ->and(app(PlanLimits::class)->generationsLeftToday($user))->toBe(2);

    clearMinuteBurst();
    expect(app(GenerationLimiter::class)->consume(2))->toBeTrue()
        ->and(app(PlanLimits::class)->generationsLeftToday($user))->toBe(0);
});

test('dailyQuotaExhausted tells a refusal caused by the daily quota apart from the burst limit', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    DB::table('ai_generations')->insert(array_fill(0, 5, [
        'user_id' => $user->id,
        'kind' => 'generate',
        'created_at' => now(),
    ]));

    clearMinuteBurst();
    expect(app(GenerationLimiter::class)->consume())->toBeFalse()
        ->and(app(GenerationLimiter::class)->dailyQuotaExhausted())->toBeTrue();
});

test('a guest shares the free daily allowance', function () {
    $this->actingAs(User::factory()->create(['is_guest' => true]));

    $granted = collect(range(1, 6))->map(fn () => spendOne());

    expect($granted->filter()->count())->toBe(5);
});

test('without a signed-in user nothing is granted', function () {
    expect(app(GenerationLimiter::class)->consume())->toBeFalse();
});

test('guests on one IP share a daily cap, so a fresh guest account brings no fresh quota', function () {
    config(['plans.guest_generations_per_ip_per_day' => 7]);

    $this->actingAs(User::factory()->guest()->create());
    $first = collect(range(1, 5))->map(fn () => spendOne())->filter()->count();

    $this->actingAs(User::factory()->guest()->create());
    $second = collect(range(1, 5))->map(fn () => spendOne())->filter()->count();

    expect($first)->toBe(5)
        ->and($second)->toBe(2)
        ->and(app(GenerationLimiter::class)->dailyQuotaExhausted())->toBeTrue();
});

test('the guest IP cap does not touch signed-up users on the same network', function () {
    config(['plans.guest_generations_per_ip_per_day' => 1]);

    $this->actingAs(User::factory()->guest()->create());
    spendOne();

    $this->actingAs(User::factory()->create());

    expect(collect(range(1, 5))->map(fn () => spendOne())->filter()->count())->toBe(5);
});

test('guests get ten AI requests a minute per IP before the burst limit refuses', function () {
    config(['plans.guest_generations_per_ip_per_day' => 100, 'plans.free_generations_per_day' => 100]);
    $this->actingAs(User::factory()->guest()->create());

    $granted = collect(range(1, 11))->map(fn () => app(GenerationLimiter::class)->consume());

    expect($granted->filter()->count())->toBe(10)
        ->and($granted->last())->toBeFalse();
});

test('guest accounts are capped per IP per day', function () {
    config(['plans.guest_accounts_per_ip_per_day' => 2]);

    $statuses = collect(range(1, 3))->map(function () {
        auth()->logout();

        return $this->post(route('guest.start'))->status();
    });

    expect($statuses->all())->toBe([302, 302, 429])
        ->and(User::where('is_guest', true)->count())->toBe(2);
});
