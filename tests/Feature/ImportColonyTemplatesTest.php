<?php

namespace Tests\Feature;

use App\Actions\GameGeneration\ImportColonyTemplates;
use App\Enums\ColonyKind;
use App\Enums\InventorySection;
use App\Enums\UnitCode;
use App\Models\Game;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImportColonyTemplatesTest extends TestCase
{
    use LazilyRefreshDatabase;

    private ImportColonyTemplates $importer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importer = new ImportColonyTemplates;
    }

    private function sampleData(): array
    {
        return json_decode(
            file_get_contents(base_path('sample-data/beta/colony-template.json')),
            true
        );
    }

    #[Test]
    public function it_creates_three_templates_from_sample_data(): void
    {
        $game = Game::factory()->create();

        $this->importer->execute($game, $this->sampleData());

        $this->assertCount(3, $game->colonyTemplates);
        $this->assertEquals(
            ['COPN', 'CORB', 'CSHP'],
            $game->colonyTemplates->pluck('kind.value')->sort()->values()->all()
        );
    }

    #[Test]
    public function it_stores_metadata_on_templates(): void
    {
        $game = Game::factory()->create();

        $this->importer->execute($game, $this->sampleData());

        $copn = $game->colonyTemplates()->where('kind', ColonyKind::OpenSurface)->first();

        $this->assertSame(1.0, $copn->sol);
        $this->assertSame(0.0625, $copn->birth_rate);
        $this->assertSame(0.0625, $copn->death_rate);
    }

    #[Test]
    public function cshp_template_has_no_population(): void
    {
        $game = Game::factory()->create();

        $this->importer->execute($game, $this->sampleData());

        $cshp = $game->colonyTemplates()->where('kind', ColonyKind::Ship)->first();

        $this->assertCount(0, $cshp->population);
    }

    #[Test]
    public function copn_template_has_population(): void
    {
        $game = Game::factory()->create();

        $this->importer->execute($game, $this->sampleData());

        $copn = $game->colonyTemplates()->where('kind', ColonyKind::OpenSurface)->first();

        $this->assertGreaterThan(0, $copn->population->count());
    }

    #[Test]
    public function copn_template_items_span_all_four_inventory_sections(): void
    {
        $game = Game::factory()->create();

        $this->importer->execute($game, $this->sampleData());

        $copn = $game->colonyTemplates()->where('kind', ColonyKind::OpenSurface)->first();
        $sections = $copn->items->pluck('inventory_section')->unique()->values();

        $this->assertCount(4, $sections);
        $this->assertTrue($sections->contains(InventorySection::SuperStructure));
        $this->assertTrue($sections->contains(InventorySection::Structure));
        $this->assertTrue($sections->contains(InventorySection::Operational));
        $this->assertTrue($sections->contains(InventorySection::Cargo));
    }

    #[Test]
    public function template_items_use_quantity_and_inventory_section(): void
    {
        $game = Game::factory()->create();

        $this->importer->execute($game, $this->sampleData());

        $copn = $game->colonyTemplates()->where('kind', ColonyKind::OpenSurface)->first();

        foreach ($copn->items as $item) {
            $this->assertNotNull($item->quantity);
            $this->assertNotNull($item->inventory_section);
            $this->assertIsInt($item->quantity);
            $this->assertInstanceOf(InventorySection::class, $item->inventory_section);
        }
    }

    #[Test]
    public function reimporting_replaces_existing_templates(): void
    {
        $game = Game::factory()->create();

        $this->importer->execute($game, $this->sampleData());
        $this->importer->execute($game, $this->sampleData());

        $this->assertCount(3, $game->fresh()->colonyTemplates);
    }

    #[Test]
    public function cshp_template_has_inventory_items(): void
    {
        $game = Game::factory()->create();

        $this->importer->execute($game, $this->sampleData());

        $cshp = $game->colonyTemplates()->where('kind', ColonyKind::Ship)->first();

        $this->assertGreaterThan(0, $cshp->items->count());
        $this->assertTrue($cshp->items->pluck('inventory_section')->contains(InventorySection::SuperStructure));
        $this->assertTrue($cshp->items->pluck('inventory_section')->contains(InventorySection::Cargo));
    }

    #[Test]
    public function copn_template_has_seven_factory_groups(): void
    {
        $game = Game::factory()->create();

        $this->importer->execute($game, $this->sampleData());

        $copn = $game->colonyTemplates()->where('kind', ColonyKind::OpenSurface)->first();

        $this->assertCount(7, $copn->factoryGroups);
    }

    #[Test]
    public function corb_template_has_one_factory_group(): void
    {
        $game = Game::factory()->create();

        $this->importer->execute($game, $this->sampleData());

        $corb = $game->colonyTemplates()->where('kind', ColonyKind::Orbital)->first();

        $this->assertCount(1, $corb->factoryGroups);
    }

    #[Test]
    public function cshp_template_has_zero_factory_groups(): void
    {
        $game = Game::factory()->create();

        $this->importer->execute($game, $this->sampleData());

        $cshp = $game->colonyTemplates()->where('kind', ColonyKind::Ship)->first();

        $this->assertCount(0, $cshp->factoryGroups);
    }

    #[Test]
    public function factory_groups_have_null_pending_orders(): void
    {
        $game = Game::factory()->create();

        $this->importer->execute($game, $this->sampleData());

        $copn = $game->colonyTemplates()->where('kind', ColonyKind::OpenSurface)->first();

        foreach ($copn->factoryGroups as $group) {
            $this->assertNull($group->pending_orders_unit);
            $this->assertNull($group->pending_orders_tech_level);
        }
    }

    #[Test]
    public function factory_units_parse_fct_1_correctly(): void
    {
        $game = Game::factory()->create();

        $this->importer->execute($game, $this->sampleData());

        $copn = $game->colonyTemplates()->where('kind', ColonyKind::OpenSurface)->first();
        $group1 = $copn->factoryGroups()->where('group_number', 1)->first();
        $unit = $group1->units->first();

        $this->assertEquals(UnitCode::Factories, $unit->unit);
        $this->assertSame(1, $unit->tech_level);
        $this->assertSame(250000, $unit->quantity);
    }

    #[Test]
    public function factory_wip_stores_correct_quarter_unit_tech_level_and_quantity(): void
    {
        $game = Game::factory()->create();

        $this->importer->execute($game, $this->sampleData());

        $copn = $game->colonyTemplates()->where('kind', ColonyKind::OpenSurface)->first();
        $group3 = $copn->factoryGroups()->where('group_number', 3)->first();

        $this->assertCount(3, $group3->wip);

        $q1 = $group3->wip->where('quarter', 1)->first();
        $this->assertEquals(UnitCode::Automation, $q1->unit);
        $this->assertSame(1, $q1->tech_level);
        $this->assertSame(93750, $q1->quantity);

        $q2 = $group3->wip->where('quarter', 2)->first();
        $this->assertSame(93750, $q2->quantity);
    }

    #[Test]
    public function reimporting_does_not_leave_duplicate_factory_groups(): void
    {
        $game = Game::factory()->create();

        $this->importer->execute($game, $this->sampleData());
        $this->importer->execute($game, $this->sampleData());

        $copn = $game->fresh()->colonyTemplates()->where('kind', ColonyKind::OpenSurface)->first();

        $this->assertCount(7, $copn->factoryGroups);
    }

    #[Test]
    public function copn_template_has_one_farm_group(): void
    {
        $game = Game::factory()->create();

        $this->importer->execute($game, $this->sampleData());

        $copn = $game->colonyTemplates()->where('kind', ColonyKind::OpenSurface)->first();

        $this->assertCount(1, $copn->farmGroups);
    }

    #[Test]
    public function corb_template_has_zero_farm_groups(): void
    {
        $game = Game::factory()->create();

        $this->importer->execute($game, $this->sampleData());

        $corb = $game->colonyTemplates()->where('kind', ColonyKind::Orbital)->first();

        $this->assertCount(0, $corb->farmGroups);
    }

    #[Test]
    public function cshp_template_has_zero_farm_groups(): void
    {
        $game = Game::factory()->create();

        $this->importer->execute($game, $this->sampleData());

        $cshp = $game->colonyTemplates()->where('kind', ColonyKind::Ship)->first();

        $this->assertCount(0, $cshp->farmGroups);
    }

    #[Test]
    public function copn_farm_group_has_four_unit_entries_with_stages_one_through_four(): void
    {
        $game = Game::factory()->create();

        $this->importer->execute($game, $this->sampleData());

        $copn = $game->colonyTemplates()->where('kind', ColonyKind::OpenSurface)->first();
        $group = $copn->farmGroups->first();

        $this->assertCount(4, $group->units);

        foreach ([1, 2, 3, 4] as $stage) {
            $unit = $group->units->where('stage', $stage)->first();
            $this->assertNotNull($unit, "Missing farm unit at stage {$stage}");
            $this->assertEquals(UnitCode::Farms, $unit->unit);
            $this->assertSame(1, $unit->tech_level);
            $this->assertSame(32500, $unit->quantity);
        }
    }

    #[Test]
    public function reimporting_does_not_leave_duplicate_farm_groups(): void
    {
        $game = Game::factory()->create();

        $this->importer->execute($game, $this->sampleData());
        $this->importer->execute($game, $this->sampleData());

        $copn = $game->fresh()->colonyTemplates()->where('kind', ColonyKind::OpenSurface)->first();

        $this->assertCount(1, $copn->farmGroups);
        $this->assertCount(4, $copn->farmGroups->first()->units);
    }

    #[Test]
    public function sample_data_creates_zero_mine_groups(): void
    {
        $game = Game::factory()->create();

        $this->importer->execute($game, $this->sampleData());

        foreach ($game->colonyTemplates()->with('mineGroups')->get() as $template) {
            $this->assertCount(0, $template->mineGroups, "Template {$template->kind->value} should have 0 mine groups");
        }
    }

    #[Test]
    public function custom_fixture_imports_mine_groups_and_units(): void
    {
        $game = Game::factory()->create();
        $data = $this->sampleData();

        $data[0]['production']['mines'] = [
            [
                'group' => 1,
                'units' => [
                    ['unit' => 'MIN-1', 'quantity' => 5000],
                    ['unit' => 'MIN-2', 'quantity' => 3000],
                ],
            ],
            [
                'group' => 2,
                'units' => [
                    ['unit' => 'MIN-1', 'quantity' => 2000],
                ],
            ],
        ];

        $this->importer->execute($game, $data);

        $copn = $game->colonyTemplates()->where('kind', ColonyKind::OpenSurface)->first();

        $this->assertCount(2, $copn->mineGroups);

        $group1 = $copn->mineGroups()->where('group_number', 1)->first();
        $this->assertCount(2, $group1->units);

        $unit = $group1->units->where('unit', UnitCode::Mines)->where('tech_level', 1)->first();
        $this->assertNotNull($unit);
        $this->assertSame(5000, $unit->quantity);

        $unit2 = $group1->units->where('tech_level', 2)->first();
        $this->assertNotNull($unit2);
        $this->assertSame(3000, $unit2->quantity);

        $group2 = $copn->mineGroups()->where('group_number', 2)->first();
        $this->assertCount(1, $group2->units);
    }

    #[Test]
    public function imported_template_mine_groups_default_deposit_id_to_null(): void
    {
        $game = Game::factory()->create();
        $data = $this->sampleData();

        $data[0]['production']['mines'] = [
            [
                'group' => 1,
                'units' => [['unit' => 'MIN-1', 'quantity' => 100]],
            ],
        ];

        $this->importer->execute($game, $data);

        $copn = $game->colonyTemplates()->where('kind', ColonyKind::OpenSurface)->first();

        foreach ($copn->mineGroups as $group) {
            $this->assertNull($group->deposit_id);
        }
    }

    #[Test]
    public function reimporting_replaces_mine_groups_cleanly(): void
    {
        $game = Game::factory()->create();
        $data = $this->sampleData();

        $data[0]['production']['mines'] = [
            [
                'group' => 1,
                'units' => [['unit' => 'MIN-1', 'quantity' => 100]],
            ],
        ];

        $this->importer->execute($game, $data);
        $this->importer->execute($game, $data);

        $copn = $game->fresh()->colonyTemplates()->where('kind', ColonyKind::OpenSurface)->first();

        $this->assertCount(1, $copn->mineGroups);
        $this->assertCount(1, $copn->mineGroups->first()->units);
    }

    #[Test]
    public function items_have_correct_unit_and_tech_level_parsing(): void
    {
        $game = Game::factory()->create();

        $this->importer->execute($game, $this->sampleData());

        $copn = $game->colonyTemplates()->where('kind', ColonyKind::OpenSurface)->first();

        $fct = $copn->items
            ->where('inventory_section', InventorySection::Operational)
            ->where('unit.value', 'FCT')
            ->first();

        $this->assertNotNull($fct);
        $this->assertSame(1, $fct->tech_level);
        $this->assertSame(850000, $fct->quantity);

        $stu = $copn->items
            ->where('inventory_section', InventorySection::SuperStructure)
            ->where('unit.value', 'STU')
            ->first();

        $this->assertNotNull($stu);
        $this->assertSame(0, $stu->tech_level);
    }
}
