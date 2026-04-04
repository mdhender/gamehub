<?php

namespace Tests\Unit\Enums;

use App\Enums\ColonyKind;
use App\Enums\PopulationClass;
use App\Enums\TurnStatus;
use App\Enums\UnitCode;
use PHPUnit\Framework\TestCase;

class LayerOneEnumsTest extends TestCase
{
    public function test_turn_status_has_exact_cases(): void
    {
        $cases = TurnStatus::cases();

        $this->assertCount(4, $cases);

        $this->assertSame('Pending', $cases[0]->name);
        $this->assertSame('pending', $cases[0]->value);

        $this->assertSame('Generating', $cases[1]->name);
        $this->assertSame('generating', $cases[1]->value);

        $this->assertSame('Completed', $cases[2]->name);
        $this->assertSame('completed', $cases[2]->value);

        $this->assertSame('Closed', $cases[3]->name);
        $this->assertSame('closed', $cases[3]->value);
    }

    public function test_colony_kind_has_exact_cases(): void
    {
        $cases = ColonyKind::cases();

        $this->assertCount(4, $cases);

        $this->assertSame('OpenSurface', $cases[0]->name);
        $this->assertSame('COPN', $cases[0]->value);

        $this->assertSame('Enclosed', $cases[1]->name);
        $this->assertSame('CENC', $cases[1]->value);

        $this->assertSame('Orbital', $cases[2]->name);
        $this->assertSame('CORB', $cases[2]->value);

        $this->assertSame('Ship', $cases[3]->name);
        $this->assertSame('CSHP', $cases[3]->value);
    }

    public function test_population_class_has_exactly_nine_cases(): void
    {
        $values = array_column(PopulationClass::cases(), 'value');

        $this->assertCount(9, $values);
        $this->assertContains('UEM', $values);
        $this->assertContains('USK', $values);
        $this->assertContains('PRO', $values);
        $this->assertContains('SLD', $values);
        $this->assertContains('CNW', $values);
        $this->assertContains('SPY', $values);
        $this->assertContains('PLC', $values);
        $this->assertContains('SAG', $values);
        $this->assertContains('TRN', $values);
    }

    public function test_unit_code_has_exactly_thirty_cases(): void
    {
        $values = array_column(UnitCode::cases(), 'value');

        $this->assertCount(30, $values);

        $expected = [
            'AUT', 'ESH', 'EWP', 'FCT', 'FRM', 'HEN', 'LAB', 'LFS', 'MIN', 'MSL',
            'PWP', 'SEN', 'SLS', 'SPD', 'STU', 'ANM', 'ASC', 'ASW', 'MSS', 'TPT',
            'MTBT', 'RPV', 'CNGD', 'FOOD', 'FUEL', 'GOLD', 'METS', 'MTSP', 'NMTS', 'RSCH',
        ];

        foreach ($expected as $code) {
            $this->assertContains($code, $values);
        }
    }
}
