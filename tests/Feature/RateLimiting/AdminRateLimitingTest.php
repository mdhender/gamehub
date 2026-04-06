<?php

namespace Tests\Feature\RateLimiting;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminRateLimitingTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function admin_mutation_routes_are_rate_limited(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($admin)->post("/admin/users/{$user->id}/send-password-reset");
        }

        $response = $this->actingAs($admin)->post("/admin/users/{$user->id}/send-password-reset");

        $response->assertTooManyRequests();
    }

    #[Test]
    public function admin_get_routes_are_not_rate_limited_by_mutation_limiter(): void
    {
        $admin = User::factory()->admin()->create();

        for ($i = 0; $i < 15; $i++) {
            $this->actingAs($admin)->get('/admin/users');
        }

        $response = $this->actingAs($admin)->get('/admin/users');

        $response->assertOk();
    }

    #[Test]
    public function single_admin_mutation_request_succeeds(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($admin)->post("/admin/users/{$user->id}/send-password-reset");

        $response->assertRedirect();
    }

    #[Test]
    public function invitation_store_is_rate_limited(): void
    {
        $admin = User::factory()->admin()->create();

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($admin)->post('/admin/invitations', [
                'email' => "user{$i}@example.com",
            ]);
        }

        $response = $this->actingAs($admin)->post('/admin/invitations', [
            'email' => 'overflow@example.com',
        ]);

        $response->assertTooManyRequests();
    }

    #[Test]
    public function invitation_destroy_is_rate_limited(): void
    {
        $admin = User::factory()->admin()->create();
        $invitations = Invitation::factory()->count(11)->create();

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($admin)->delete("/admin/invitations/{$invitations[$i]->id}");
        }

        $response = $this->actingAs($admin)->delete("/admin/invitations/{$invitations[10]->id}");

        $response->assertTooManyRequests();
    }
}
