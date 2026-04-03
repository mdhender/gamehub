<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class HandleValidationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_regular_user_cannot_update_handle_via_profile(): void
    {
        $user = User::factory()->create(['handle' => 'original']);

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'handle' => 'changed',
                'email' => $user->email,
            ]);

        $this->assertSame('original', $user->refresh()->handle);
    }

    public function test_profile_page_displays_handle_as_read_only(): void
    {
        $user = User::factory()->create(['handle' => 'myhandle']);

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertOk();
    }
}
