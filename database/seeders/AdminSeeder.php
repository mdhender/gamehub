<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        if (App::environment('production')) {
            $this->command->error('AdminSeeder cannot run in production. Use the app:create-admin-user command instead.');

            return;
        }

        User::factory()->admin()->create([
            'name' => 'Penny Guin',
            'handle' => 'penguin',
            'email' => 'penguin@gamehub.test',
            'password' => 'happy.cat.happy.nap',
        ]);
    }
}
