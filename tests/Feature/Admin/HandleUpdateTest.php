<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class HandleUpdateTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_can_update_user_handle(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['handle' => 'oldhandle']);

        $response = $this->actingAs($admin)
            ->patch(route('admin.users.handle.update', $user), [
                'handle' => 'newhandle',
            ]);

        $response->assertRedirect();
        $this->assertSame('newhandle', $user->refresh()->handle);
    }

    public function test_handle_is_stored_lowercased(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->patch(route('admin.users.handle.update', $user), [
                'handle' => 'MyHandle',
            ]);

        $this->assertSame('myhandle', $user->refresh()->handle);
    }

    public function test_handle_must_be_unique_case_insensitively(): void
    {
        User::factory()->create(['handle' => 'taken']);
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($admin)
            ->patch(route('admin.users.handle.update', $user), [
                'handle' => 'Taken',
            ]);

        $response->assertSessionHasErrors('handle');
    }

    public function test_handle_allows_letters_numbers_underscores_hyphens_and_single_quotes(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($admin)
            ->patch(route('admin.users.handle.update', $user), [
                'handle' => "o'neil-test_123",
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame("o'neil-test_123", $user->refresh()->handle);
    }

    public function test_handle_rejects_spaces(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($admin)
            ->patch(route('admin.users.handle.update', $user), [
                'handle' => 'has space',
            ]);

        $response->assertSessionHasErrors('handle');
    }

    public function test_handle_rejects_html_special_characters(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($admin)
            ->patch(route('admin.users.handle.update', $user), [
                'handle' => '<script>',
            ]);

        $response->assertSessionHasErrors('handle');
    }

    public function test_handle_is_required(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($admin)
            ->patch(route('admin.users.handle.update', $user), [
                'handle' => '',
            ]);

        $response->assertSessionHasErrors('handle');
    }

    public function test_admin_can_keep_users_existing_handle(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['handle' => 'myhandle']);

        $response = $this->actingAs($admin)
            ->patch(route('admin.users.handle.update', $user), [
                'handle' => 'myhandle',
            ]);

        $response->assertSessionHasNoErrors();
    }

    public function test_non_admin_cannot_update_handle(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create(['handle' => 'original']);

        $response = $this->actingAs($user)
            ->patch(route('admin.users.handle.update', $target), [
                'handle' => 'changed',
            ]);

        $response->assertForbidden();
        $this->assertSame('original', $target->refresh()->handle);
    }

    public function test_guest_cannot_update_handle(): void
    {
        $user = User::factory()->create(['handle' => 'original']);

        $response = $this->patch(route('admin.users.handle.update', $user), [
            'handle' => 'changed',
        ]);

        $response->assertRedirect('/login');
        $this->assertSame('original', $user->refresh()->handle);
    }
}
