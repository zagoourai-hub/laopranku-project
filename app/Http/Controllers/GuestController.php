<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class GuestController extends Controller
{
    /**
     * Start a trial session as a throwaway guest user; registering later upgrades this same row.
     */
    public function __invoke(): RedirectResponse
    {
        // No factory here: Faker is a dev dependency and is absent on a `--no-dev` production install.
        $guest = (new User)->forceFill([
            'name' => 'Tamu',
            'email' => 'guest-'.Str::uuid().'@guest.invalid',
            'password' => Str::random(40),
            'email_verified_at' => now(),
            'is_guest' => true,
        ]);
        $guest->save();

        Auth::login($guest);

        request()->session()->regenerate();

        return redirect()->route('documents.create');
    }
}
