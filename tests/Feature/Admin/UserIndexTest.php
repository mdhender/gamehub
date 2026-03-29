<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserIndexTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function admin_can_view_users_list(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($admin)->get('/admin/users');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/users')
            ->has('users', 2)
        );
    }

    #[Test]
    public function non_admin_cannot_view_users_list(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/users')->assertForbidden();
    }

    #[Test]
    public function guest_cannot_view_users_list(): void
    {
        $this->get('/admin/users')->assertRedirect('/login');
    }

    #[Test]
    public function users_list_includes_expected_fields(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/admin/users');

        $response->assertInertia(fn ($page) => $page
            ->component('admin/users')
            ->has('users.0', fn ($user) => $user
                ->has('id')
                ->has('name')
                ->has('email')
                ->has('is_admin')
                ->has('created_at')
            )
        );
    }
}
