<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SendPasswordResetLinkTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function admin_can_send_password_reset_link(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($admin)->post("/admin/users/{$user->id}/send-password-reset");

        $response->assertRedirect();
        $response->assertSessionHas('success');

        Notification::assertSentTo($user, \Illuminate\Auth\Notifications\ResetPassword::class);
    }

    #[Test]
    public function non_admin_cannot_send_password_reset_link(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($user)->post("/admin/users/{$otherUser->id}/send-password-reset")->assertForbidden();
    }

    #[Test]
    public function guest_cannot_send_password_reset_link(): void
    {
        $user = User::factory()->create();

        $this->post("/admin/users/{$user->id}/send-password-reset")->assertRedirect('/login');
    }

    #[Test]
    public function admin_can_send_password_reset_link_to_themselves(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post("/admin/users/{$admin->id}/send-password-reset");

        $response->assertRedirect();
        $response->assertSessionHas('success');

        Notification::assertSentTo($admin, \Illuminate\Auth\Notifications\ResetPassword::class);
    }
}
