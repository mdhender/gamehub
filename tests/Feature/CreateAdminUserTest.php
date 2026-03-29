<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateAdminUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_admin_user()
    {
        $this->artisan('app:create-admin-user', [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'secret-password',
        ])
            ->assertSuccessful();

        $user = User::where('email', 'admin@example.com')->first();

        $this->assertNotNull($user);
        $this->assertEquals('Admin User', $user->name);
        $this->assertTrue($user->is_admin);
    }

    public function test_it_fails_if_email_already_exists()
    {
        User::factory()->create(['email' => 'admin@example.com']);

        $this->artisan('app:create-admin-user', [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'secret-password',
        ])
            ->assertFailed();
    }

    public function test_password_is_hashed()
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
