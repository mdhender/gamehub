<?php

namespace Tests\Feature\Policies;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvitationPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function test_view_allows_admin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $invitation = Invitation::factory()->create();

        $this->actingAs($admin);
        $this->assertTrue(Gate::allows('view', $invitation));
    }

    #[Test]
    public function test_view_denies_non_admin(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $invitation = Invitation::factory()->create();

        $this->actingAs($user);
        $this->assertFalse(Gate::allows('view', $invitation));
    }

    #[Test]
    public function test_update_allows_admin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $invitation = Invitation::factory()->create();

        $this->actingAs($admin);
        $this->assertTrue(Gate::allows('update', $invitation));
    }

    #[Test]
    public function test_update_denies_non_admin(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $invitation = Invitation::factory()->create();

        $this->actingAs($user);
        $this->assertFalse(Gate::allows('update', $invitation));
    }
}
