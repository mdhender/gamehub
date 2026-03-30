<?php

namespace Tests\Feature;

use App\Enums\GameRole;
use App\Models\Game;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    #[Test]
    public function authenticated_users_can_visit_the_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }

    #[Test]
    public function dashboard_passes_active_games_count_and_current_game(): void
    {
        $user = User::factory()->create();
        $active = Game::factory()->create();
        Game::factory()->inactive()->create();
        $active->users()->attach($user, ['role' => GameRole::Player->value]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('activeGamesCount', 1)
            ->where('currentGame.id', $active->id)
        );
    }

    #[Test]
    public function current_game_is_the_most_recently_updated_active_game(): void
    {
        $user = User::factory()->create();
        $older = Game::factory()->create(['updated_at' => now()->subDay()]);
        $newer = Game::factory()->create(['updated_at' => now()]);
        $older->users()->attach($user, ['role' => GameRole::Gm->value]);
        $newer->users()->attach($user, ['role' => GameRole::Gm->value]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->where('currentGame.id', $newer->id)
        );
    }

    #[Test]
    public function inactive_games_are_excluded_from_count_and_current_game(): void
    {
        $user = User::factory()->create();
        $inactive = Game::factory()->inactive()->create();
        $inactive->users()->attach($user, ['role' => GameRole::Gm->value]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->where('activeGamesCount', 0)
            ->where('currentGame', null)
        );
    }

    #[Test]
    public function admin_sees_all_active_games_count(): void
    {
        $admin = User::factory()->admin()->create();
        Game::factory()->count(3)->create();
        Game::factory()->inactive()->create();

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->where('activeGamesCount', 3)
        );
    }

    #[Test]
    public function non_member_sees_zero_active_games(): void
    {
        $user = User::factory()->create();
        Game::factory()->count(2)->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->where('activeGamesCount', 0)
            ->where('currentGame', null)
        );
    }

    #[Test]
    public function admin_sees_user_stats(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->count(2)->create();

        Invitation::factory()->count(2)->create();
        Invitation::factory()->expired()->create();
        Invitation::factory()->registered()->create();

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->where('totalActiveUsers', 3)
            ->where('pendingInvitesCount', 2)
        );
    }

    #[Test]
    public function admin_sees_logged_in_users_count(): void
    {
        $admin = User::factory()->admin()->create();
        $otherUser = User::factory()->create();

        DB::table('sessions')->insert([
            'id' => 'session-active',
            'user_id' => $otherUser->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => '',
            'last_activity' => now()->subMinutes(5)->timestamp,
        ]);

        DB::table('sessions')->insert([
            'id' => 'session-expired',
            'user_id' => $otherUser->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => '',
            'last_activity' => now()->subMinutes(30)->timestamp,
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->where('loggedInUsersCount', 1)
        );
    }

    #[Test]
    public function non_admin_does_not_receive_user_stats(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->missing('totalActiveUsers')
            ->missing('loggedInUsersCount')
            ->missing('pendingInvitesCount')
        );
    }
}
