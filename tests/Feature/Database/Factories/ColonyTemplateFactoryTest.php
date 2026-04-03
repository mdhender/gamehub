<?php

namespace Tests\Feature\Database\Factories;

use App\Enums\ColonyKind;
use App\Models\ColonyTemplate;
use App\Models\Game;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ColonyTemplateFactoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function factory_creates_a_valid_template(): void
    {
        $template = ColonyTemplate::factory()->create();

        $this->assertModelExists($template);
    }

    #[Test]
    public function default_kind_is_enum_backed(): void
    {
        $template = ColonyTemplate::factory()->create();

        $this->assertSame(ColonyKind::OpenSurface, $template->fresh()->kind);
        $this->assertDatabaseHas('colony_templates', ['id' => $template->id, 'kind' => 'COPN']);
    }

    #[Test]
    public function multiple_templates_per_game(): void
    {
        $game = Game::factory()->create();

        $first = ColonyTemplate::factory()->create(['game_id' => $game->id]);
        $second = ColonyTemplate::factory()->create(['game_id' => $game->id]);

        $this->assertModelExists($first);
        $this->assertModelExists($second);
    }
}
