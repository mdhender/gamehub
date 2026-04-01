<?php

namespace Tests\Feature\Admin;

use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function admin_can_view_invitations_page(): void
    {
        $admin = User::factory()->admin()->create();
        Invitation::factory()->count(3)->create();

        $response = $this->actingAs($admin)->get('/admin/invitations');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/invitations')
            ->has('invitations', 3)
        );
    }

    #[Test]
    public function non_admin_cannot_view_invitations_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/invitations')->assertForbidden();
    }

    #[Test]
    public function guest_cannot_view_invitations_page(): void
    {
        $this->get('/admin/invitations')->assertRedirect('/login');
    }

    #[Test]
    public function admin_can_send_invitation(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post('/admin/invitations', [
            'email' => 'newuser@example.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('invitations', [
            'email' => 'newuser@example.com',
        ]);

        Mail::assertQueued(InvitationMail::class, function (InvitationMail $mail) {
            return $mail->hasTo('newuser@example.com');
        });
    }

    #[Test]
    public function invitation_requires_valid_email(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post('/admin/invitations', [
            'email' => 'not-an-email',
        ]);

        $response->assertSessionHasErrors('email');
        Mail::assertNothingQueued();
    }

    #[Test]
    public function cannot_invite_already_registered_email(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->actingAs($admin)->post('/admin/invitations', [
            'email' => 'existing@example.com',
        ]);

        $response->assertSessionHasErrors('email');
        Mail::assertNothingQueued();
    }

    #[Test]
    public function cannot_invite_email_with_pending_invitation(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        Invitation::factory()->create(['email' => 'pending@example.com']);

        $response = $this->actingAs($admin)->post('/admin/invitations', [
            'email' => 'pending@example.com',
        ]);

        $response->assertSessionHasErrors('email');
        Mail::assertNothingQueued();
    }

    #[Test]
    public function can_invite_email_whose_previous_invitation_was_used(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        Invitation::factory()->registered()->create(['email' => 'reusable@example.com']);

        $response = $this->actingAs($admin)->post('/admin/invitations', [
            'email' => 'reusable@example.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        Mail::assertQueued(InvitationMail::class);
    }

    #[Test]
    public function non_admin_cannot_send_invitation(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->post('/admin/invitations', [
            'email' => 'newuser@example.com',
        ])->assertForbidden();

        Mail::assertNothingQueued();
    }

    #[Test]
    public function invitations_list_includes_expected_fields(): void
    {
        $admin = User::factory()->admin()->create();
        Invitation::factory()->create();

        $response = $this->actingAs($admin)->get('/admin/invitations');

        $response->assertInertia(fn ($page) => $page
            ->component('admin/invitations')
            ->has('invitations.0', fn ($invitation) => $invitation
                ->has('id')
                ->has('email')
                ->has('expires_at')
                ->has('registered_at')
                ->has('created_at')
            )
        );
    }

    #[Test]
    public function admin_can_delete_invitation(): void
    {
        $admin = User::factory()->admin()->create();
        $invitation = Invitation::factory()->create();

        $response = $this->actingAs($admin)->delete("/admin/invitations/{$invitation->id}");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('invitations', ['id' => $invitation->id]);
    }

    #[Test]
    public function non_admin_cannot_delete_invitation(): void
    {
        $user = User::factory()->create();
        $invitation = Invitation::factory()->create();

        $this->actingAs($user)->delete("/admin/invitations/{$invitation->id}")->assertForbidden();

        $this->assertDatabaseHas('invitations', ['id' => $invitation->id]);
    }

    #[Test]
    public function admin_can_resend_invitation(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $invitation = Invitation::factory()->create([
            'email' => 'resend@example.com',
        ]);
        $originalToken = $invitation->token;

        $response = $this->actingAs($admin)->post("/admin/invitations/{$invitation->id}/resend");

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $invitation->refresh();
        $this->assertNotEquals($originalToken, $invitation->token);
        $this->assertTrue($invitation->expires_at->isFuture());

        Mail::assertQueued(InvitationMail::class, function (InvitationMail $mail) {
            return $mail->hasTo('resend@example.com');
        });
    }

    #[Test]
    public function invitation_email_contains_recipient_email_notice(): void
    {
        $invitation = Invitation::factory()->create(['email' => 'specific@example.com']);
        $mail = new InvitationMail($invitation);

        $rendered = $mail->render();

        $this->assertStringContainsString('specific@example.com', $rendered);
        $this->assertStringContainsString('must register using', $rendered);
    }

    #[Test]
    public function non_admin_cannot_resend_invitation(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $invitation = Invitation::factory()->create();

        $this->actingAs($user)->post("/admin/invitations/{$invitation->id}/resend")->assertForbidden();

        Mail::assertNothingQueued();
    }
}
