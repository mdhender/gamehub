<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Penny Guin',
            'email' => 'penguin@gamehub.test',
            'password' => 'happy.cat.happy.nap',
        ]);
    }
}
