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
        Auth::login(User::factory()->guest()->create([
            'email' => 'guest-'.Str::uuid().'@guest.invalid',
        ]));

        request()->session()->regenerate();

        return redirect()->route('documents.create');
    }
}
