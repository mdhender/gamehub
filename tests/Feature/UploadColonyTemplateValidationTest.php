<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UploadColonyTemplateValidationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function upload(Game $game, User $user, string $json): TestResponse
    {
        $tmp = tempnam(sys_get_temp_dir(), 'col');
        file_put_contents($tmp, $json);
        $file = new UploadedFile($tmp, 'colony.json', 'application/json', null, true);

        return $this->actingAs($user)
            ->post("/games/{$game->id}/generate/templates/colony", ['template' => $file]);
    }

    private function gmUser(Game $game): User
    {
        $user = User::factory()->create();
        $game->users()->attach($user, ['role' => 'gm', 'is_active' => true]);

        return $user;
    }

    private function validTemplate(string $kind = 'COPN'): array
    {
        return [
            'kind' => $kind,
            'tech-level' => 1,
            'sol' => 1.0,
            'birth-rate-pct' => 0.0625,
            'death-rate-pct' => 0.0625,
            'population' => [
                ['population_code' => 'UEM', 'quantity' => 1000, 'pay_rate' => 0.5],
            ],
            'inventory' => [
                'operational' => [
                    ['unit' => 'FCT-1', 'quantity' => 10],
                ],
            ],
        ];
    }

    #[Test]
    public function valid_single_template_passes_validation(): void
    {
        $game = Game::factory()->create();
        $user = $this->gmUser($game);

        $this->upload($game, $user, json_encode([$this->validTemplate()]))
            ->assertSessionDoesntHaveErrors('template');
    }

    #[Test]
    public function valid_two_template_upload_passes_validation(): void
    {
        $game = Game::factory()->create();
        $user = $this->gmUser($game);

        $payload = [
            $this->validTemplate('COPN'),
            $this->validTemplate('CORB'),
        ];

        $this->upload($game, $user, json_encode($payload))
            ->assertSessionDoesntHaveErrors('template');
    }

    #[Test]
    public function non_array_top_level_fails(): void
    {
        $game = Game::factory()->create();
        $user = $this->gmUser($game);

        $this->upload($game, $user, json_encode($this->validTemplate()))
            ->assertSessionHasErrors('template');
    }

    #[Test]
    public function empty_array_fails(): void
    {
        $game = Game::factory()->create();
        $user = $this->gmUser($game);

        $this->upload($game, $user, json_encode([]))
            ->assertSessionHasErrors('template');
    }

    #[Test]
    public function missing_kind_fails(): void
    {
        $game = Game::factory()->create();
        $user = $this->gmUser($game);

        $template = $this->validTemplate();
        unset($template['kind']);

        $this->upload($game, $user, json_encode([$template]))
            ->assertSessionHasErrors('template');
    }

    #[Test]
    public function invalid_kind_value_fails(): void
    {
        $game = Game::factory()->create();
        $user = $this->gmUser($game);

        $template = $this->validTemplate();
        $template['kind'] = 'INVALID';

        $this->upload($game, $user, json_encode([$template]))
            ->assertSessionHasErrors('template');
    }

    #[Test]
    public function missing_tech_level_fails(): void
    {
        $game = Game::factory()->create();
        $user = $this->gmUser($game);

        $template = $this->validTemplate();
        unset($template['tech-level']);

        $this->upload($game, $user, json_encode([$template]))
            ->assertSessionHasErrors('template');
    }

    #[Test]
    public function missing_population_fails(): void
    {
        $game = Game::factory()->create();
        $user = $this->gmUser($game);

        $template = $this->validTemplate();
        unset($template['population']);

        $this->upload($game, $user, json_encode([$template]))
            ->assertSessionHasErrors('template');
    }

    #[Test]
    public function empty_population_array_passes_for_cshp(): void
    {
        $game = Game::factory()->create();
        $user = $this->gmUser($game);

        $template = $this->validTemplate('CSHP');
        $template['population'] = [];

        $this->upload($game, $user, json_encode([$template]))
            ->assertSessionDoesntHaveErrors('template');
    }

    #[Test]
    public function missing_inventory_fails(): void
    {
        $game = Game::factory()->create();
        $user = $this->gmUser($game);

        $template = $this->validTemplate();
        unset($template['inventory']);

        $this->upload($game, $user, json_encode([$template]))
            ->assertSessionHasErrors('template');
    }

    #[Test]
    public function empty_inventory_all_sections_fails(): void
    {
        $game = Game::factory()->create();
        $user = $this->gmUser($game);

        $template = $this->validTemplate();
        $template['inventory'] = [
            'super-structure' => [],
            'structure' => [],
            'operational' => [],
            'cargo' => [],
        ];

        $this->upload($game, $user, json_encode([$template]))
            ->assertSessionHasErrors('template');
    }

    #[Test]
    public function non_consumable_without_tech_level_suffix_fails(): void
    {
        $game = Game::factory()->create();
        $user = $this->gmUser($game);

        $template = $this->validTemplate();
        $template['inventory']['operational'] = [['unit' => 'FCT', 'quantity' => 10]];

        $this->upload($game, $user, json_encode([$template]))
            ->assertSessionHasErrors('template');
    }

    #[Test]
    public function consumable_with_tech_level_suffix_fails(): void
    {
        $game = Game::factory()->create();
        $user = $this->gmUser($game);

        $template = $this->validTemplate();
        $template['inventory']['operational'] = [['unit' => 'FUEL-1', 'quantity' => 10]];

        $this->upload($game, $user, json_encode([$template]))
            ->assertSessionHasErrors('template');
    }

    #[Test]
    public function invalid_unit_code_fails(): void
    {
        $game = Game::factory()->create();
        $user = $this->gmUser($game);

        $template = $this->validTemplate();
        $template['inventory']['operational'] = [['unit' => 'INVALID-1', 'quantity' => 10]];

        $this->upload($game, $user, json_encode([$template]))
            ->assertSessionHasErrors('template');
    }

    #[Test]
    public function duplicate_kind_values_fail(): void
    {
        $game = Game::factory()->create();
        $user = $this->gmUser($game);

        $payload = [
            $this->validTemplate('COPN'),
            $this->validTemplate('COPN'),
        ];

        $this->upload($game, $user, json_encode($payload))
            ->assertSessionHasErrors('template');
    }

    #[Test]
    public function valid_non_consumable_with_tech_level_passes(): void
    {
        $game = Game::factory()->create();
        $user = $this->gmUser($game);

        $template = $this->validTemplate();
        $template['inventory']['operational'] = [['unit' => 'FCT-1', 'quantity' => 10]];

        $this->upload($game, $user, json_encode([$template]))
            ->assertSessionDoesntHaveErrors('template');
    }

    #[Test]
    public function cadre_population_without_pay_rate_passes_validation(): void
    {
        $game = Game::factory()->create();
        $user = $this->gmUser($game);

        $template = $this->validTemplate();
        $template['population'] = [
            ['population_code' => 'USK', 'quantity' => 1000, 'pay_rate' => 0.125],
            ['population_code' => 'PRO', 'quantity' => 100, 'pay_rate' => 0.375],
            ['population_code' => 'CNW', 'quantity' => 10],
            ['population_code' => 'SPY', 'quantity' => 5],
        ];

        $this->upload($game, $user, json_encode([$template]))
            ->assertSessionDoesntHaveErrors('template');
    }

    #[Test]
    public function non_cadre_population_without_pay_rate_fails_validation(): void
    {
        $game = Game::factory()->create();
        $user = $this->gmUser($game);

        $template = $this->validTemplate();
        $template['population'] = [
            ['population_code' => 'USK', 'quantity' => 1000],
        ];

        $this->upload($game, $user, json_encode([$template]))
            ->assertSessionHasErrors('template');
    }

    #[Test]
    public function valid_consumable_without_tech_level_passes(): void
    {
        $game = Game::factory()->create();
        $user = $this->gmUser($game);

        $template = $this->validTemplate();
        $template['inventory']['operational'] = [['unit' => 'FUEL', 'quantity' => 10]];

        $this->upload($game, $user, json_encode([$template]))
            ->assertSessionDoesntHaveErrors('template');
    }

    #[Test]
    public function missing_sol_fails(): void
    {
        $game = Game::factory()->create();
        $user = $this->gmUser($game);

        $template = $this->validTemplate();
        unset($template['sol']);

        $this->upload($game, $user, json_encode([$template]))
            ->assertSessionHasErrors('template');
    }

    #[Test]
    public function missing_birth_rate_pct_fails(): void
    {
        $game = Game::factory()->create();
        $user = $this->gmUser($game);

        $template = $this->validTemplate();
        unset($template['birth-rate-pct']);

        $this->upload($game, $user, json_encode([$template]))
            ->assertSessionHasErrors('template');
    }

    #[Test]
    public function missing_death_rate_pct_fails(): void
    {
        $game = Game::factory()->create();
        $user = $this->gmUser($game);

        $template = $this->validTemplate();
        unset($template['death-rate-pct']);

        $this->upload($game, $user, json_encode([$template]))
            ->assertSessionHasErrors('template');
    }

    #[Test]
    public function unknown_inventory_section_key_fails(): void
    {
        $game = Game::factory()->create();
        $user = $this->gmUser($game);

        $template = $this->validTemplate();
        $template['inventory'] = [
            'operational' => [['unit' => 'FCT-1', 'quantity' => 10]],
            'weapons' => [['unit' => 'FCT-1', 'quantity' => 5]],
        ];

        $this->upload($game, $user, json_encode([$template]))
            ->assertSessionHasErrors('template');
    }

    #[Test]
    public function cshp_with_empty_population_and_inventory_passes(): void
    {
        $game = Game::factory()->create();
        $user = $this->gmUser($game);

        $template = $this->validTemplate('CSHP');
        $template['population'] = [];
        $template['inventory'] = [
            'super-structure' => [['unit' => 'SLS', 'quantity' => 500000]],
            'structure' => [['unit' => 'LFS-1', 'quantity' => 500]],
            'operational' => [],
            'cargo' => [['unit' => 'FUEL', 'quantity' => 5000]],
        ];

        $this->upload($game, $user, json_encode([$template]))
            ->assertSessionDoesntHaveErrors('template');
    }

    #[Test]
    public function template_with_all_four_inventory_sections_passes(): void
    {
        $game = Game::factory()->create();
        $user = $this->gmUser($game);

        $template = $this->validTemplate();
        $template['inventory'] = [
            'super-structure' => [['unit' => 'STU', 'quantity' => 1000]],
            'structure' => [['unit' => 'SEN-1', 'quantity' => 5]],
            'operational' => [['unit' => 'FCT-1', 'quantity' => 10]],
            'cargo' => [['unit' => 'FUEL', 'quantity' => 100]],
        ];

        $this->upload($game, $user, json_encode([$template]))
            ->assertSessionDoesntHaveErrors('template');
    }

    #[Test]
    public function copn_and_corb_templates_from_sample_data_pass(): void
    {
        $game = Game::factory()->create();
        $user = $this->gmUser($game);

        $json = file_get_contents(base_path('sample-data/beta/colony-template.json'));

        $this->upload($game, $user, $json)
            ->assertSessionDoesntHaveErrors('template');
    }
}
