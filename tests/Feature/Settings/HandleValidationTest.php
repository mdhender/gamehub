<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class HandleValidationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_handle_is_stored_lowercased(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'handle' => 'MyHandle',
                'email' => $user->email,
            ]);

        $this->assertSame('myhandle', $user->refresh()->handle);
    }

    public function test_handle_must_be_unique_case_insensitively(): void
    {
        User::factory()->create(['handle' => 'taken']);
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'handle' => 'Taken',
                'email' => $user->email,
            ]);

        $response->assertSessionHasErrors('handle');
    }

    public function test_handle_allows_letters_numbers_underscores_hyphens_and_single_quotes(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'handle' => "o'neil-test_123",
                'email' => $user->email,
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame("o'neil-test_123", $user->refresh()->handle);
    }

    public function test_handle_rejects_spaces(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'handle' => 'has space',
                'email' => $user->email,
            ]);

        $response->assertSessionHasErrors('handle');
    }

    public function test_handle_rejects_html_special_characters(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'handle' => '<script>',
                'email' => $user->email,
            ]);

        $response->assertSessionHasErrors('handle');
    }

    public function test_handle_is_required(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'handle' => '',
                'email' => $user->email,
            ]);

        $response->assertSessionHasErrors('handle');
    }

    public function test_user_can_keep_their_own_handle(): void
    {
        $user = User::factory()->create(['handle' => 'myhandle']);

        $response = $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'handle' => 'myhandle',
                'email' => $user->email,
            ]);

        $response->assertSessionHasNoErrors();
    }
}
