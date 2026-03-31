<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GameGenerationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeHomeSystemTemplateJson(int $planetCount = 1, int $homeworldCount = 1): string
    {
        $planets = [];

        for ($i = 0; $i < $planetCount; $i++) {
            $planets[] = [
                'orbit' => $i + 1,
                'type' => 'terrestrial',
                'habitability' => 10,
                'homeworld' => $homeworldCount > 0 && $i === 0,
                'deposits' => [
                    ['resource' => 'gold', 'yield_pct' => 5, 'quantity_remaining' => 1000],
                ],
            ];
        }

        if ($homeworldCount > 1) {
            $planets[1]['homeworld'] = true;
        }

        return json_encode(['planets' => $planets]);
    }

    private function makeColonyTemplateJson(int $itemCount = 1): string
    {
        $inventory = [];

        for ($i = 0; $i < $itemCount; $i++) {
            $inventory[] = [
                'unit' => $i + 1,
                'TechLevel' => 1,
                'QuantityAssembled' => 1000,
                'QuantityDisassembled' => 0,
            ];
        }

        return json_encode([
            'Kind' => 1,
            'TechLevel' => 1,
            'inventory' => $inventory,
        ]);
    }

    private function jsonFile(string $filename, string $content): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'tpl');
        file_put_contents($tmp, $content);

        return new UploadedFile($tmp, $filename, 'application/json', null, true);
    }

    private function gmUser(Game $game): User
    {
        $user = User::factory()->create();
        $game->users()->attach($user, ['role' => 'gm', 'is_active' => true]);

        return $user;
    }

    // -------------------------------------------------------------------------
    // show
    // -------------------------------------------------------------------------

    #[Test]
    public function generate_page_renders_for_authorized_user(): void
    {
        $game = Game::factory()->create();
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->get("/games/{$game->id}/generate")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('games/generate')
                ->has('game')
                ->has('homeSystemTemplate')
                ->has('colonyTemplate')
            );
    }

    #[Test]
    public function generate_page_passes_template_summary_when_templates_exist(): void
    {
        $game = Game::factory()->withDefaultTemplates()->create();
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->get("/games/{$game->id}/generate")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('homeSystemTemplate.planet_count', 6)
                ->whereNot('homeSystemTemplate.homeworld_orbit', null)
                ->where('colonyTemplate.unit_count', 7)
            );
    }

    #[Test]
    public function generate_page_is_forbidden_for_non_gm(): void
    {
        $game = Game::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get("/games/{$game->id}/generate")
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // uploadHomeSystemTemplate
    // -------------------------------------------------------------------------

    #[Test]
    public function upload_home_system_template_creates_template_and_children(): void
    {
        $game = Game::factory()->create();
        $user = $this->gmUser($game);

        $file = $this->jsonFile('template.json', $this->makeHomeSystemTemplateJson());

        $this->actingAs($user)
            ->post("/games/{$game->id}/generate/templates/home-system", ['template' => $file])
            ->assertRedirect();

        $this->assertNotNull($game->fresh()->homeSystemTemplate);
        $this->assertSame(1, $game->homeSystemTemplate()->first()->planets()->count());
    }

    #[Test]
    public function upload_home_system_template_replaces_existing_template(): void
    {
        $game = Game::factory()->withDefaultTemplates()->create();
        $user = $this->gmUser($game);

        $existingTemplate = $game->homeSystemTemplate;
        $file = $this->jsonFile('template.json', $this->makeHomeSystemTemplateJson());

        $this->actingAs($user)
            ->post("/games/{$game->id}/generate/templates/home-system", ['template' => $file])
            ->assertRedirect();

        $this->assertModelMissing($existingTemplate);
        $this->assertSame(1, $game->homeSystemTemplate()->first()->planets()->count());
    }

    #[Test]
    public function upload_home_system_template_is_rejected_when_game_is_active(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Active]);
        $user = $this->gmUser($game);

        $file = $this->jsonFile('template.json', $this->makeHomeSystemTemplateJson());

        $this->actingAs($user)
            ->post("/games/{$game->id}/generate/templates/home-system", ['template' => $file])
            ->assertSessionHasErrors('template');
    }

    #[Test]
    public function upload_home_system_template_is_rejected_when_no_planets(): void
    {
        $game = Game::factory()->create();
        $user = $this->gmUser($game);

        $file = $this->jsonFile('template.json', json_encode(['planets' => []]));

        $this->actingAs($user)
            ->post("/games/{$game->id}/generate/templates/home-system", ['template' => $file])
            ->assertSessionHasErrors('template');
    }

    #[Test]
    public function upload_home_system_template_is_rejected_when_no_homeworld(): void
    {
        $game = Game::factory()->create();
        $user = $this->gmUser($game);

        $json = json_encode(['planets' => [
            ['orbit' => 1, 'type' => 'terrestrial', 'habitability' => 10, 'homeworld' => false, 'deposits' => []],
        ]]);

        $file = $this->jsonFile('template.json', $json);

        $this->actingAs($user)
            ->post("/games/{$game->id}/generate/templates/home-system", ['template' => $file])
            ->assertSessionHasErrors('template');
    }

    #[Test]
    public function upload_home_system_template_is_rejected_when_multiple_homeworlds(): void
    {
        $game = Game::factory()->create();
        $user = $this->gmUser($game);

        $file = $this->jsonFile('template.json', $this->makeHomeSystemTemplateJson(planetCount: 2, homeworldCount: 2));

        $this->actingAs($user)
            ->post("/games/{$game->id}/generate/templates/home-system", ['template' => $file])
            ->assertSessionHasErrors('template');
    }

    // -------------------------------------------------------------------------
    // uploadColonyTemplate
    // -------------------------------------------------------------------------

    #[Test]
    public function upload_colony_template_creates_template_and_items(): void
    {
        $game = Game::factory()->create();
        $user = $this->gmUser($game);

        $file = $this->jsonFile('colony.json', $this->makeColonyTemplateJson(itemCount: 3));

        $this->actingAs($user)
            ->post("/games/{$game->id}/generate/templates/colony", ['template' => $file])
            ->assertRedirect();

        $this->assertNotNull($game->fresh()->colonyTemplate);
        $this->assertSame(3, $game->colonyTemplate()->first()->items()->count());
    }

    #[Test]
    public function upload_colony_template_replaces_existing_template(): void
    {
        $game = Game::factory()->withDefaultTemplates()->create();
        $user = $this->gmUser($game);

        $existingTemplate = $game->colonyTemplate;
        $file = $this->jsonFile('colony.json', $this->makeColonyTemplateJson());

        $this->actingAs($user)
            ->post("/games/{$game->id}/generate/templates/colony", ['template' => $file])
            ->assertRedirect();

        $this->assertModelMissing($existingTemplate);
        $this->assertSame(1, $game->colonyTemplate()->first()->items()->count());
    }

    #[Test]
    public function upload_colony_template_is_rejected_when_game_is_active(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Active]);
        $user = $this->gmUser($game);

        $file = $this->jsonFile('colony.json', $this->makeColonyTemplateJson());

        $this->actingAs($user)
            ->post("/games/{$game->id}/generate/templates/colony", ['template' => $file])
            ->assertSessionHasErrors('template');
    }

    #[Test]
    public function upload_colony_template_is_rejected_when_no_inventory_items(): void
    {
        $game = Game::factory()->create();
        $user = $this->gmUser($game);

        $file = $this->jsonFile('colony.json', json_encode(['Kind' => 1, 'TechLevel' => 1, 'inventory' => []]));

        $this->actingAs($user)
            ->post("/games/{$game->id}/generate/templates/colony", ['template' => $file])
            ->assertSessionHasErrors('template');
    }
}
