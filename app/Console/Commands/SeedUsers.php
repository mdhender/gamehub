<?php

namespace App\Console\Commands;

use Database\Seeders\UserSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;

#[Signature('app:seed-users {count=1 : The number of users to create (max 250)}')]
#[Description('Seed deterministic test users in non-production environments')]
class SeedUsers extends Command
{
    public function handle(): int
    {
        if (App::environment('production')) {
            $this->error('This command cannot run in production.');

            return self::FAILURE;
        }

        $count = (int) $this->argument('count');

        if ($count < 1 || $count > 250) {
            $this->error('Count must be between 1 and 250.');

            return self::FAILURE;
        }

        $seeder = new UserSeeder;
        $seeder->setCommand($this);
        $seeder->run($count);

        return self::SUCCESS;
    }
}
