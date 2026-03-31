<?php

namespace Tests\Feature;

use App\Models\HomeSystem;
use App\Models\Planet;
use App\Models\Star;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StarModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function star_has_one_home_system(): void
    {
        $star = Star::factory()->create();
        $planet = Planet::factory()->create(['game_id' => $star->game_id, 'star_id' => $star->id]);
        $homeSystem = HomeSystem::factory()->create([
            'game_id' => $star->game_id,
            'star_id' => $star->id,
            'homeworld_planet_id' => $planet->id,
        ]);

        $this->assertTrue($star->homeSystem->is($homeSystem));
    }

    #[Test]
    public function star_home_system_is_null_when_not_assigned(): void
    {
        $star = Star::factory()->create();

        $this->assertNull($star->homeSystem);
    }
}
