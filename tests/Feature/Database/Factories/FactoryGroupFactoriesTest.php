<?php

namespace Tests\Feature\Database\Factories;

use App\Enums\UnitCode;
use App\Models\ColonyFactoryGroup;
use App\Models\ColonyFactoryUnit;
use App\Models\ColonyFactoryWip;
use App\Models\ColonyTemplateFactoryGroup;
use App\Models\ColonyTemplateFactoryUnit;
use App\Models\ColonyTemplateFactoryWip;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FactoryGroupFactoriesTest extends TestCase
{
    use LazilyRefreshDatabase;

    // ── Template factories ──────────────────────────────────────

    #[Test]
    public function template_group_factory_makes_valid_attributes(): void
    {
        $attrs = ColonyTemplateFactoryGroup::factory()->make()->toArray();

        $this->assertArrayHasKey('group_number', $attrs);
        $this->assertArrayHasKey('orders_unit', $attrs);
        $this->assertArrayHasKey('orders_tech_level', $attrs);
        $this->assertNull($attrs['pending_orders_unit']);
        $this->assertNull($attrs['pending_orders_tech_level']);
    }

    #[Test]
    public function template_group_factory_creates_record(): void
    {
        $group = ColonyTemplateFactoryGroup::factory()->create();

        $this->assertModelExists($group);
    }

    #[Test]
    public function template_unit_factory_makes_valid_attributes(): void
    {
        $attrs = ColonyTemplateFactoryUnit::factory()->make()->toArray();

        $this->assertSame(UnitCode::Factories->value, $attrs['unit']);
        $this->assertGreaterThan(0, $attrs['tech_level']);
    }

    #[Test]
    public function template_unit_factory_creates_record(): void
    {
        $unit = ColonyTemplateFactoryUnit::factory()->create();

        $this->assertModelExists($unit);
    }

    #[Test]
    public function template_wip_factory_makes_valid_attributes(): void
    {
        $attrs = ColonyTemplateFactoryWip::factory()->make()->toArray();

        $this->assertContains($attrs['quarter'], [1, 2, 3]);
        $this->assertSame(UnitCode::Factories->value, $attrs['unit']);
    }

    #[Test]
    public function template_wip_factory_creates_record(): void
    {
        $wip = ColonyTemplateFactoryWip::factory()->create();

        $this->assertModelExists($wip);
    }

    // ── Live colony factories ───────────────────────────────────

    #[Test]
    public function live_group_factory_makes_valid_attributes(): void
    {
        $attrs = ColonyFactoryGroup::factory()->make()->toArray();

        $this->assertNull($attrs['pending_orders_unit']);
        $this->assertNull($attrs['pending_orders_tech_level']);
        $this->assertSame(0.0, (float) $attrs['input_remainder_mets']);
        $this->assertSame(0.0, (float) $attrs['input_remainder_nmts']);
    }

    #[Test]
    public function live_group_factory_creates_record(): void
    {
        $group = ColonyFactoryGroup::factory()->create();

        $this->assertModelExists($group);
    }

    #[Test]
    public function live_unit_factory_makes_valid_attributes(): void
    {
        $attrs = ColonyFactoryUnit::factory()->make()->toArray();

        $this->assertSame(UnitCode::Factories->value, $attrs['unit']);
        $this->assertGreaterThan(0, $attrs['tech_level']);
    }

    #[Test]
    public function live_unit_factory_creates_record(): void
    {
        $unit = ColonyFactoryUnit::factory()->create();

        $this->assertModelExists($unit);
    }

    #[Test]
    public function live_wip_factory_makes_valid_attributes(): void
    {
        $attrs = ColonyFactoryWip::factory()->make()->toArray();

        $this->assertContains($attrs['quarter'], [1, 2, 3]);
        $this->assertSame(UnitCode::Factories->value, $attrs['unit']);
    }

    #[Test]
    public function live_wip_factory_creates_record(): void
    {
        $wip = ColonyFactoryWip::factory()->create();

        $this->assertModelExists($wip);
    }

    // ── Relationship wiring ─────────────────────────────────────

    #[Test]
    public function template_group_factory_creates_related_units_and_wip(): void
    {
        $group = ColonyTemplateFactoryGroup::factory()->create();
        $unit = ColonyTemplateFactoryUnit::factory()->create(['colony_template_factory_group_id' => $group->id]);
        $wip = ColonyTemplateFactoryWip::factory()->create(['colony_template_factory_group_id' => $group->id]);

        $this->assertTrue($group->units->contains($unit));
        $this->assertTrue($group->wip->contains($wip));
    }

    #[Test]
    public function live_group_factory_creates_related_units_and_wip(): void
    {
        $group = ColonyFactoryGroup::factory()->create();
        $unit = ColonyFactoryUnit::factory()->create(['colony_factory_group_id' => $group->id]);
        $wip = ColonyFactoryWip::factory()->create(['colony_factory_group_id' => $group->id]);

        $this->assertTrue($group->units->contains($unit));
        $this->assertTrue($group->wip->contains($wip));
    }
}
