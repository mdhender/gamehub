<?php

namespace Tests\Feature\Database\Factories;

use App\Enums\UnitCode;
use App\Models\ColonyTemplateItem;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ColonyTemplateItemFactoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function factory_creates_a_valid_row(): void
    {
        $item = ColonyTemplateItem::factory()->create();

        $this->assertModelExists($item);
    }

    #[Test]
    public function default_unit_resolves_as_unit_code(): void
    {
        $item = ColonyTemplateItem::factory()->create();

        $this->assertInstanceOf(UnitCode::class, $item->fresh()->unit);
    }

    #[Test]
    public function raw_db_value_is_a_valid_enum_backing_value(): void
    {
        $item = ColonyTemplateItem::factory()->create();

        $validValues = array_map(fn (UnitCode $code) => $code->value, UnitCode::cases());

        $this->assertContains($item->fresh()->unit->value, $validValues);
    }

    #[Test]
    public function explicit_override_works(): void
    {
        $item = ColonyTemplateItem::factory()->create(['unit' => UnitCode::Metals]);

        $this->assertSame(UnitCode::Metals, $item->fresh()->unit);
    }
}
