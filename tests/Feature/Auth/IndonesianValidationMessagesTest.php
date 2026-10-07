<?php

use App\Models\User;

test('failed login shows the Indonesian credentials message', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'salah-total'])
        ->assertSessionHasErrors(['email' => 'Email atau kata sandi salah.']);
});

test('login without credentials asks for them in Indonesian', function () {
    $this->post(route('login.store'), [])
        ->assertSessionHasErrors(['email' => 'Email wajib diisi.', 'password' => 'Kata sandi wajib diisi.']);
});

test('registration rejects bad input with Indonesian messages', function (array $overrides, string $field, string $message) {
    User::factory()->create(['email' => 'sudah@ada.test']);

    $this->post(route('register.store'), $overrides + [
        'name' => 'Rina Putri',
        'email' => 'rina@contoh.test',
        'password' => 'Sandi-Aman-123',
        'password_confirmation' => 'Sandi-Aman-123',
    ])->assertSessionHasErrors([$field => $message]);
})->with([
    'missing name' => [['name' => ''], 'name', 'Nama wajib diisi.'],
    'malformed email' => [['email' => 'bukan-email'], 'email', 'Format email tidak valid.'],
    'duplicate email' => [['email' => 'sudah@ada.test'], 'email', 'Email sudah terdaftar.'],
    'short password' => [['password' => 'Ab1!', 'password_confirmation' => 'Ab1!'], 'password', 'Kata sandi minimal 8 karakter.'],
    'mismatched confirmation' => [['password_confirmation' => 'Beda-Sama-Sekali'], 'password', 'Konfirmasi kata sandi tidak cocok.'],
]);

test('password reset for an unknown email says so in Indonesian', function () {
    $this->post(route('password.email'), ['email' => 'tidak-ada@contoh.test'])
        ->assertSessionHasErrors(['email' => 'Tidak ada akun dengan email tersebut.']);
});

test('login throttle message names the seconds to wait', function () {
    expect(trans('auth.throttle', ['seconds' => 30]))
        ->toBe('Terlalu banyak percobaan masuk. Coba lagi dalam 30 detik.');
});
