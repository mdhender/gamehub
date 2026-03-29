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

        Mail::assertSent(InvitationMail::class, function (InvitationMail $mail) {
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
        Mail::assertNothingSent();
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
        Mail::assertNothingSent();
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
        Mail::assertNothingSent();
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
        Mail::assertSent(InvitationMail::class);
    }

    #[Test]
    public function non_admin_cannot_send_invitation(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->post('/admin/invitations', [
            'email' => 'newuser@example.com',
        ])->assertForbidden();

        Mail::assertNothingSent();
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
}
