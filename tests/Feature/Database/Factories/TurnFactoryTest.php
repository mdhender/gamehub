<?php

namespace Tests\Feature\Database\Factories;

use App\Enums\TurnStatus;
use App\Models\Game;
use App\Models\Turn;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TurnFactoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function test_factory_creates_a_valid_turn(): void
    {
        $turn = Turn::factory()->create();

        $this->assertInstanceOf(Turn::class, $turn);
    }

    #[Test]
    public function test_factory_defaults_number_to_zero(): void
    {
        $turn = Turn::factory()->create();

        $this->assertSame(0, $turn->number);
    }

    #[Test]
    public function test_factory_defaults_status_to_pending(): void
    {
        $turn = Turn::factory()->create();

        $this->assertSame(TurnStatus::Pending, $turn->status);
    }

    #[Test]
    public function test_factory_defaults_reports_locked_at_to_null(): void
    {
        $turn = Turn::factory()->create();

        $this->assertNull($turn->reports_locked_at);
    }

    #[Test]
    public function test_factory_auto_creates_game(): void
    {
        $turn = Turn::factory()->create();

        $this->assertInstanceOf(Game::class, $turn->game);
    }

    #[Test]
    public function test_factory_accepts_attribute_overrides(): void
    {
        $turn = Turn::factory()->create([
            'number' => 5,
            'status' => TurnStatus::Completed,
        ]);

        $this->assertSame(5, $turn->number);
        $this->assertSame(TurnStatus::Completed, $turn->status);
    }
}
