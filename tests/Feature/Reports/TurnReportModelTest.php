<?php

namespace Tests\Feature\Reports;

use App\Models\Empire;
use App\Models\Game;
use App\Models\Turn;
use App\Models\TurnReport;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TurnReportModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeTurnReport(): TurnReport
    {
        $game = Game::factory()->create();
        $turn = Turn::factory()->create(['game_id' => $game->id]);
        $empire = Empire::factory()->create(['game_id' => $game->id]);

        return TurnReport::query()->create([
            'game_id' => $game->id,
            'turn_id' => $turn->id,
            'empire_id' => $empire->id,
            'generated_at' => now(),
        ]);
    }

    #[Test]
    public function test_turn_report_casts_generated_at_to_carbon(): void
    {
        $report = $this->makeTurnReport();

        $this->assertInstanceOf(\DateTimeInterface::class, $report->fresh()->generated_at);
    }

    #[Test]
    public function test_turn_report_belongs_to_game_turn_and_empire(): void
    {
        $game = Game::factory()->create();
        $turn = Turn::factory()->create(['game_id' => $game->id]);
        $empire = Empire::factory()->create(['game_id' => $game->id]);

        $report = TurnReport::query()->create([
            'game_id' => $game->id,
            'turn_id' => $turn->id,
            'empire_id' => $empire->id,
            'generated_at' => now(),
        ]);

        $this->assertTrue($report->game->is($game));
        $this->assertTrue($report->turn->is($turn));
        $this->assertTrue($report->empire->is($empire));
    }

    #[Test]
    public function test_turn_report_has_many_colonies_and_surveys(): void
    {
        $report = $this->makeTurnReport();

        DB::table('turn_report_colonies')->insert([
            [
                'turn_report_id' => $report->id,
                'name' => 'Alpha',
                'kind' => 'COPN',
                'tech_level' => 1,
                'orbit' => 1,
                'star_x' => 0,
                'star_y' => 0,
                'star_z' => 0,
                'star_sequence' => 1,
                'is_on_surface' => true,
                'rations' => 1.0,
                'sol' => 0.0,
                'birth_rate' => 0.0,
                'death_rate' => 0.0,
            ],
            [
                'turn_report_id' => $report->id,
                'name' => 'Beta',
                'kind' => 'CENC',
                'tech_level' => 2,
                'orbit' => 2,
                'star_x' => 1,
                'star_y' => 1,
                'star_z' => 1,
                'star_sequence' => 1,
                'is_on_surface' => false,
                'rations' => 1.0,
                'sol' => 0.0,
                'birth_rate' => 0.0,
                'death_rate' => 0.0,
            ],
        ]);

        DB::table('turn_report_surveys')->insert([
            'turn_report_id' => $report->id,
            'orbit' => 3,
            'star_x' => 5,
            'star_y' => 5,
            'star_z' => 5,
            'star_sequence' => 1,
            'planet_type' => 'TERR',
            'habitability' => 70,
        ]);

        $this->assertSame(2, $report->colonies()->count());
        $this->assertSame(1, $report->surveys()->count());
    }
}
