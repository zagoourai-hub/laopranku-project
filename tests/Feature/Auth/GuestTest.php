<?php

use App\Models\Document;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

test('starting a trial creates a verified guest user, logs in and redirects to document creation', function () {
    $this->post(route('guest.start'))->assertRedirect(route('documents.create'));

    $user = User::sole();
    expect($user->is_guest)->toBeTrue()
        ->and($user->email)->toEndWith('@guest.invalid')
        ->and($user->hasVerifiedEmail())->toBeTrue();
    $this->assertAuthenticatedAs($user);

    $this->get(route('documents.create'))->assertOk();
});

test('a logged-in real user cannot start a trial', function () {
    $this->actingAs(User::factory()->create())->post(route('guest.start'))->assertRedirect();

    expect(User::count())->toBe(1);
});

test('a guest is redirected away from login and cannot start another trial', function () {
    $guest = User::factory()->guest()->create();

    $this->actingAs($guest)->get(route('login'))->assertRedirect();
    $this->actingAs($guest)->post(route('guest.start'))->assertRedirect();

    expect(User::count())->toBe(1);
});

test('registering while a guest upgrades the same user and keeps the documents', function () {
    Notification::fake();
    $guest = User::factory()->guest()->create();
    $document = Document::factory()->for($guest)->create();

    $this->actingAs($guest)->get(route('register'))->assertOk();
    $this->actingAs($guest)->post(route('register.store'), [
        'name' => 'Budi',
        'email' => 'budi@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect();

    $guest->refresh();
    expect(User::count())->toBe(1)
        ->and($guest->is_guest)->toBeFalse()
        ->and($guest->email)->toBe('budi@example.com')
        ->and($document->fresh()->user_id)->toBe($guest->id);
    $this->assertAuthenticatedAs($guest);
    Notification::assertSentTo($guest, VerifyEmail::class);
});

test('a guest cannot register with an email that another account uses', function () {
    User::factory()->create(['email' => 'taken@example.com']);
    $guest = User::factory()->guest()->create();

    $this->actingAs($guest)->post(route('register.store'), [
        'name' => 'Budi',
        'email' => 'taken@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('email');

    expect($guest->fresh()->is_guest)->toBeTrue();
});

test('starting a trial is throttled', function () {
    foreach (range(1, 10) as $i) {
        $this->post(route('guest.start'))->assertRedirect();
        auth()->logout();
    }

    $this->post(route('guest.start'))->assertTooManyRequests();
});

test('only guests see the sign-up banner', function () {
    $this->actingAs(User::factory()->guest()->create())->get(route('dashboard'))
        ->assertSee('data-test="guest-banner"', false)
        ->assertSee(route('register'));

    $this->actingAs(User::factory()->create())->get(route('dashboard'))
        ->assertDontSee('data-test="guest-banner"', false);
});

test('the create form does not prefill the placeholder guest name', function () {
    Livewire\Livewire::actingAs(User::factory()->guest()->create(['name' => 'Tamu']))
        ->test('pages::documents.create')
        ->assertSet('name', '');
});
