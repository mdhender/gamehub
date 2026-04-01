<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SeedUsersTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function it_creates_one_user_by_default()
    {
        $this->artisan('app:seed-users')
            ->assertSuccessful()
            ->expectsOutputToContain('Created 1 user(s), skipped 0 existing.');

        $user = User::where('email', 'user1@gamehub.test')->first();

        $this->assertNotNull($user);
        $this->assertSame('User 1', $user->name);
        $this->assertFalse($user->is_admin);
    }

    #[Test]
    public function it_creates_the_specified_number_of_users()
    {
        $this->artisan('app:seed-users', ['count' => 5])
            ->assertSuccessful()
            ->expectsOutputToContain('Created 5 user(s), skipped 0 existing.');

        $this->assertSame(5, User::count());

        for ($i = 1; $i <= 5; $i++) {
            $user = User::where('email', "user{$i}@gamehub.test")->first();
            $this->assertNotNull($user);
            $this->assertSame("User {$i}", $user->name);
        }
    }

    #[Test]
    public function it_skips_existing_users_on_second_run()
    {
        $this->artisan('app:seed-users', ['count' => 3])->assertSuccessful();
        $this->assertSame(3, User::count());

        $this->artisan('app:seed-users', ['count' => 5])
            ->assertSuccessful()
            ->expectsOutputToContain('Created 2 user(s), skipped 3 existing.');

        $this->assertSame(5, User::count());
    }

    #[Test]
    public function it_rejects_count_above_250()
    {
        $this->artisan('app:seed-users', ['count' => 251])
            ->assertFailed()
            ->expectsOutputToContain('Count must be between 1 and 250.');

        $this->assertSame(0, User::count());
    }

    #[Test]
    public function it_rejects_count_below_1()
    {
        $this->artisan('app:seed-users', ['count' => 0])
            ->assertFailed()
            ->expectsOutputToContain('Count must be between 1 and 250.');

        $this->assertSame(0, User::count());
    }

    #[Test]
    public function users_have_deterministic_names_and_emails()
    {
        $this->artisan('app:seed-users', ['count' => 3])
            ->assertSuccessful();

        $this->assertNotNull(User::where('name', 'User 1')->where('email', 'user1@gamehub.test')->first());
        $this->assertNotNull(User::where('name', 'User 2')->where('email', 'user2@gamehub.test')->first());
        $this->assertNotNull(User::where('name', 'User 3')->where('email', 'user3@gamehub.test')->first());
    }
}
