<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
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
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'invitation_token' => ['required', 'string'],
        ])->validate();

        $invitation = Invitation::query()
            ->valid()
            ->where('email', $input['email'])
            ->where('token', $input['invitation_token'])
            ->first();

        if (! $invitation) {
            throw ValidationException::withMessages([
                'invitation_token' => __('The invitation is invalid, expired, or does not match this email address.'),
            ]);
        }

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

        $invitation->markAsRegistered();

        return $user;
    }
}
