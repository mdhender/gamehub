<?php

namespace Tests\Feature\Models;

use App\Enums\ColonyKind;
use App\Models\Colony;
use App\Models\Empire;
use App\Models\Planet;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ColonyModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeColony(array $attributes = []): Colony
    {
        $empire = Empire::factory()->create();
        $planet = Planet::factory()->create();

        return Colony::query()->create(array_merge([
            'empire_id' => $empire->id,
            'star_id' => $planet->star_id,
            'planet_id' => $planet->id,
            'kind' => ColonyKind::Enclosed,
            'tech_level' => 1,
            'name' => 'Test Colony',
            'rations' => 1.0,
            'sol' => 0.0,
            'birth_rate' => 0.0,
            'death_rate' => 0.0,
        ], $attributes));
    }

    #[Test]
    public function casts_kind_to_colony_kind(): void
    {
        $colony = $this->makeColony(['kind' => ColonyKind::Enclosed]);

        $this->assertSame(ColonyKind::Enclosed, $colony->fresh()->kind);
    }

    #[Test]
    public function mass_assignment_accepts_new_fillable_columns(): void
    {
        $colony = $this->makeColony([
            'name' => 'Alpha Base',
            'rations' => 2.5,
            'sol' => 1.5,
            'birth_rate' => 0.1,
            'death_rate' => 0.05,
        ]);

        $fresh = $colony->fresh();
        $this->assertSame('Alpha Base', $fresh->name);
        $this->assertSame(2.5, $fresh->rations);
        $this->assertSame(1.5, $fresh->sol);
        $this->assertSame(0.1, $fresh->birth_rate);
        $this->assertSame(0.05, $fresh->death_rate);
    }

    #[Test]
    public function primitive_casts_round_trip_correctly(): void
    {
        $colony = $this->makeColony([
            'rations' => 1.0,
            'sol' => 0.0,
            'birth_rate' => 0.0,
            'death_rate' => 0.0,
        ]);

        $fresh = $colony->fresh();
        $this->assertIsFloat($fresh->rations);
        $this->assertIsFloat($fresh->sol);
        $this->assertIsFloat($fresh->birth_rate);
        $this->assertIsFloat($fresh->death_rate);
    }

    #[Test]
    public function raw_database_stores_enum_backing_value(): void
    {
        $this->makeColony(['kind' => ColonyKind::Enclosed]);

        $this->assertDatabaseHas('colonies', ['kind' => 'CENC']);
    }

    #[Test]
    public function existing_relationships_still_work(): void
    {
        $empire = Empire::factory()->create();
        $planet = Planet::factory()->create();

        $colony = Colony::query()->create([
            'empire_id' => $empire->id,
            'star_id' => $planet->star_id,
            'planet_id' => $planet->id,
            'kind' => ColonyKind::OpenSurface,
            'tech_level' => 1,
        ]);

        $this->assertTrue($colony->empire->is($empire));
        $this->assertTrue($colony->star->is($planet->star));
        $this->assertTrue($colony->planet->is($planet));
    }
}
