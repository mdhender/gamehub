<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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
        $input['handle'] = mb_strtolower($input['handle'] ?? '');

        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'invitation_token' => ['required', 'string'],
        ], [
            'invitation_token.required' => __('This registration page is invite-only. Please check your email for an invitation link.'),
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

        return DB::transaction(function () use ($input, $invitation): User {
            $user = User::create([
                'name' => $input['name'],
                'handle' => $input['handle'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            $invitation->markAsRegistered();

            return $user;
        });
    }
}
