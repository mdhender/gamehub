<?php

namespace Tests\Feature\Auth;

use App\Models\Invitation;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register_with_valid_invitation(): void
    {
        $invitation = Invitation::factory()->create([
            'email' => 'test@example.com',
        ]);

        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'invitation_token' => $invitation->token,
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $invitation->refresh();
        $this->assertNotNull($invitation->registered_at);
    }

    public function test_registration_fails_without_invitation_token(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('invitation_token');
    }

    public function test_registration_fails_with_invalid_invitation_token(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'invitation_token' => 'invalid-token',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('invitation_token');
    }

    public function test_registration_fails_with_expired_invitation(): void
    {
        $invitation = Invitation::factory()->expired()->create([
            'email' => 'test@example.com',
        ]);

        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'invitation_token' => $invitation->token,
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('invitation_token');
    }

    public function test_registration_fails_with_already_used_invitation(): void
    {
        $invitation = Invitation::factory()->registered()->create([
            'email' => 'test@example.com',
        ]);

        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'invitation_token' => $invitation->token,
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('invitation_token');
    }

    public function test_registration_fails_when_email_does_not_match_invitation(): void
    {
        $invitation = Invitation::factory()->create([
            'email' => 'invited@example.com',
        ]);

        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'different@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'invitation_token' => $invitation->token,
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('invitation_token');
    }
}
