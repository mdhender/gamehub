<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;

class UserSeeder extends Seeder
{
    public function run(int $count = 1): void
    {
        if (App::environment('production')) {
            $this->command?->error('UserSeeder cannot run in production.');

            return;
        }

        $count = min(max($count, 1), 250);

        for ($i = 1; $i <= $count; $i++) {
            User::factory()->create([
                'name' => "User {$i}",
                'email' => "user{$i}@gamehub.test",
                'password' => 'happy.cat.happy.nap',
            ]);
        }

        $this->command?->info("Created {$count} user(s).");
    }
}
