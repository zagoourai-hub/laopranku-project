<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        // A guest registering upgrades the same row, so their documents stay with the account.
        $guest = Auth::user()?->is_guest ? Auth::user() : null;

        Validator::make($input, [
            ...$this->profileRules($guest?->id),
            'password' => $this->passwordRules(),
        ])->validate();

        if ($guest) {
            $guest->forceFill([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
                'is_guest' => false,
                'email_verified_at' => null,
            ])->save();

            return $guest;
        }

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);
    }
}
