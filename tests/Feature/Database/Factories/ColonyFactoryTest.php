<?php

namespace Tests\Feature\Database\Factories;

use App\Enums\ColonyKind;
use App\Models\Colony;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ColonyFactoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function factory_creates_a_valid_colony(): void
    {
        $colony = Colony::factory()->create();

        $this->assertModelExists($colony);
    }

    #[Test]
    public function default_kind_is_enum_backed(): void
    {
        $colony = Colony::factory()->create();

        $this->assertSame(ColonyKind::OpenSurface, $colony->fresh()->kind);
        $this->assertDatabaseHas('colonies', ['id' => $colony->id, 'kind' => 'COPN']);
    }

    #[Test]
    public function new_columns_have_coherent_defaults(): void
    {
        $colony = Colony::factory()->create();
        $fresh = $colony->fresh();

        $this->assertSame('Not Named', $fresh->name);
        $this->assertSame(1.0, $fresh->rations);
        $this->assertSame(0.0, $fresh->sol);
        $this->assertSame(0.0, $fresh->birth_rate);
        $this->assertSame(0.0, $fresh->death_rate);
    }

    #[Test]
    public function overrides_work(): void
    {
        $colony = Colony::factory()->create([
            'kind' => ColonyKind::Orbital,
        ]);

        $fresh = $colony->fresh();
        $this->assertSame(ColonyKind::Orbital, $fresh->kind);
    }
}
