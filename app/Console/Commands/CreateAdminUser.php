<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Support\Facades\Hash;

#[Signature('app:create-admin-user {name} {email} {password}')]
#[Description('Create an admin user for production use')]
class CreateAdminUser extends Command implements PromptsForMissingInput
{
    public function handle(): int
    {
        $email = $this->argument('email');

        if (User::where('email', $email)->exists()) {
            $this->error("A user with email [{$email}] already exists.");

            return self::FAILURE;
        }

        $user = new User([
            'name' => $this->argument('name'),
            'email' => $email,
            'password' => Hash::make($this->argument('password')),
        ]);
        $user->is_admin = true;
        $user->save();

        $this->info("Admin user [{$user->name}] created with email [{$user->email}].");

        return self::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    protected function promptForMissingArgumentsUsing(): array
    {
        return [
            'name' => 'What is the admin user\'s name?',
            'email' => 'What is the admin user\'s email address?',
            'password' => 'What password should be set?',
        ];
    }
}
