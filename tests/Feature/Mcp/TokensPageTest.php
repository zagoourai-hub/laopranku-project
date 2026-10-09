<?php

use App\Models\User;
use Livewire\Livewire;

test('a user creates a token, sees it once and can revoke it', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)->test('pages::settings.tokens')
        ->set('name', 'Claude Desktop')
        ->call('create')
        ->assertHasNoErrors()
        ->assertSee('hanya tampil sekali');

    expect($user->tokens()->count())->toBe(1);
    expect($component->get('newToken'))->toContain('|');

    $component->call('revoke', $user->tokens()->first()->id)->assertSet('newToken', null);

    expect($user->tokens()->count())->toBe(0);
});

test('token name is required', function () {
    Livewire::actingAs(User::factory()->create())->test('pages::settings.tokens')
        ->call('create')
        ->assertHasErrors(['name' => 'required']);
});

test('a user cannot revoke another user token', function () {
    $other = User::factory()->create();
    $other->createToken('milik orang lain');

    Livewire::actingAs(User::factory()->create())->test('pages::settings.tokens')
        ->call('revoke', $other->tokens()->first()->id);

    expect($other->tokens()->count())->toBe(1);
});

test('the tokens page renders for a verified user and shows the endpoint', function () {
    $this->actingAs(User::factory()->create())->get(route('settings.tokens'))
        ->assertOk()
        ->assertSee(url('/mcp'));
});

test('guests are told to register instead of getting a token form', function () {
    $this->actingAs(User::factory()->guest()->create())->get(route('settings.tokens'))
        ->assertOk()
        ->assertSee('Daftar akun dulu')
        ->assertDontSee('Buat token');
});
