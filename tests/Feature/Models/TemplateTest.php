<?php

namespace Tests\Feature\Models;

use App\Enums\DepositResource;
use App\Enums\PlanetType;
use App\Models\ColonyTemplate;
use App\Models\ColonyTemplateItem;
use App\Models\Game;
use App\Models\HomeSystemTemplate;
use App\Models\HomeSystemTemplateDeposit;
use App\Models\HomeSystemTemplatePlanet;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TemplateTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function game_has_one_home_system_template(): void
    {
        $game = Game::factory()->create();
        $template = HomeSystemTemplate::factory()->create(['game_id' => $game->id]);

        $this->assertTrue($game->homeSystemTemplate->is($template));
    }

    #[Test]
    public function game_has_one_colony_template(): void
    {
        $game = Game::factory()->create();
        $template = ColonyTemplate::factory()->create(['game_id' => $game->id]);

        $this->assertTrue($game->colonyTemplate->is($template));
    }

    #[Test]
    public function home_system_template_has_many_planets(): void
    {
        $template = HomeSystemTemplate::factory()->create();
        $planet = HomeSystemTemplatePlanet::factory()->create(['home_system_template_id' => $template->id]);

        $this->assertTrue($template->planets->contains($planet));
    }

    #[Test]
    public function home_system_template_planet_has_many_deposits(): void
    {
        $planet = HomeSystemTemplatePlanet::factory()->create();
        $deposit = HomeSystemTemplateDeposit::factory()->create(['home_system_template_planet_id' => $planet->id]);

        $this->assertTrue($planet->deposits->contains($deposit));
    }

    #[Test]
    public function colony_template_has_many_items(): void
    {
        $template = ColonyTemplate::factory()->create();
        $item = ColonyTemplateItem::factory()->create(['colony_template_id' => $template->id]);

        $this->assertTrue($template->items->contains($item));
    }

    #[Test]
    public function home_system_template_planet_casts_type_to_enum(): void
    {
        $planet = HomeSystemTemplatePlanet::factory()->create(['type' => PlanetType::Terrestrial]);

        $this->assertSame(PlanetType::Terrestrial, $planet->fresh()->type);
    }

    #[Test]
    public function home_system_template_deposit_casts_resource_to_enum(): void
    {
        $deposit = HomeSystemTemplateDeposit::factory()->create(['resource' => DepositResource::Gold]);

        $this->assertSame(DepositResource::Gold, $deposit->fresh()->resource);
    }

    #[Test]
    public function home_system_template_planet_defaults_is_homeworld_to_false(): void
    {
        $planet = HomeSystemTemplatePlanet::factory()->create();

        $this->assertFalse($planet->is_homeworld);
    }

    #[Test]
    public function home_system_template_cascades_delete_to_planets_and_deposits(): void
    {
        $template = HomeSystemTemplate::factory()->create();
        $planet = HomeSystemTemplatePlanet::factory()->create(['home_system_template_id' => $template->id]);
        HomeSystemTemplateDeposit::factory()->create(['home_system_template_planet_id' => $planet->id]);

        $template->delete();

        $this->assertModelMissing($planet);
        $this->assertDatabaseEmpty('home_system_template_deposits');
    }

    #[Test]
    public function colony_template_cascades_delete_to_items(): void
    {
        $template = ColonyTemplate::factory()->create();
        $item = ColonyTemplateItem::factory()->create(['colony_template_id' => $template->id]);

        $template->delete();

        $this->assertModelMissing($item);
    }
}
