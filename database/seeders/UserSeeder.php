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

        $created = 0;
        $skipped = 0;

        for ($i = 1; $i <= $count; $i++) {
            $email = "user{$i}@gamehub.test";

            if (User::where('email', $email)->exists()) {
                $skipped++;

                continue;
            }

            User::factory()->create([
                'name' => "User {$i}",
                'email' => $email,
                'password' => 'happy.cat.happy.nap',
            ]);

            $created++;
        }

        $this->command?->info("Created {$created} user(s), skipped {$skipped} existing.");
    }
}
