<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserShowTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function admin_can_view_any_user_detail(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($admin)->get("/admin/users/{$user->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/users/show')
            ->has('user')
            ->where('user.id', $user->id)
            ->where('user.name', $user->name)
            ->where('user.email', $user->email)
        );
    }

    #[Test]
    public function user_can_view_their_own_detail(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get("/admin/users/{$user->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/users/show')
            ->where('user.id', $user->id)
        );
    }

    #[Test]
    public function user_cannot_view_another_users_detail(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($user)->get("/admin/users/{$otherUser->id}")->assertForbidden();
    }

    #[Test]
    public function guest_cannot_view_user_detail(): void
    {
        $user = User::factory()->create();

        $this->get("/admin/users/{$user->id}")->assertRedirect('/login');
    }

    #[Test]
    public function user_detail_includes_expected_fields(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($admin)->get("/admin/users/{$user->id}");

        $response->assertInertia(fn ($page) => $page
            ->component('admin/users/show')
            ->has('user', fn ($prop) => $prop
                ->has('id')
                ->has('name')
                ->has('email')
                ->has('is_admin')
                ->has('email_verified_at')
                ->has('created_at')
                ->has('updated_at')
            )
        );
    }

    #[Test]
    public function user_detail_does_not_expose_sensitive_fields(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($admin)->get("/admin/users/{$user->id}");

        $response->assertInertia(fn ($page) => $page
            ->component('admin/users/show')
            ->has('user', fn ($prop) => $prop
                ->missing('password')
                ->missing('remember_token')
                ->missing('two_factor_secret')
                ->missing('two_factor_recovery_codes')
                ->etc()
            )
        );
    }
}
