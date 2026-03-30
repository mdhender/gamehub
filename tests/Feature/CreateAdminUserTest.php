<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CreateAdminUserTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function it_creates_an_admin_user()
    {
        $this->artisan('app:create-admin-user', [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'secret-password',
        ])
            ->assertSuccessful();

        $user = User::where('email', 'admin@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('Admin User', $user->name);
        $this->assertTrue($user->is_admin);
    }

    #[Test]
    public function it_fails_if_email_already_exists()
    {
        User::factory()->create(['email' => 'admin@example.com']);

        $this->artisan('app:create-admin-user', [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'secret-password',
        ])
            ->assertFailed();
    }

    #[Test]
    public function password_is_hashed()
    {
        $this->artisan('app:create-admin-user', [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'secret-password',
        ])
            ->assertSuccessful();

        $user = User::where('email', 'admin@example.com')->first();

        $this->assertNotEquals('secret-password', $user->password);
        $this->assertTrue(password_verify('secret-password', $user->password));
    }
}
