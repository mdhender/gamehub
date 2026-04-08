<?php

namespace Tests\Unit\Support;

use App\Support\FactoryProperties;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FactoryPropertiesTest extends TestCase
{
    #[Test]
    public function pro_per_unit_returns_correct_bracket_values(): void
    {
        $this->assertSame(6, FactoryProperties::proPerUnit(1));
        $this->assertSame(6, FactoryProperties::proPerUnit(4));
        $this->assertSame(5, FactoryProperties::proPerUnit(5));
        $this->assertSame(5, FactoryProperties::proPerUnit(49));
        $this->assertSame(4, FactoryProperties::proPerUnit(50));
        $this->assertSame(4, FactoryProperties::proPerUnit(499));
        $this->assertSame(3, FactoryProperties::proPerUnit(500));
        $this->assertSame(3, FactoryProperties::proPerUnit(4999));
        $this->assertSame(2, FactoryProperties::proPerUnit(5000));
        $this->assertSame(2, FactoryProperties::proPerUnit(49999));
        $this->assertSame(1, FactoryProperties::proPerUnit(50000));
        $this->assertSame(1, FactoryProperties::proPerUnit(250000));
    }

    #[Test]
    public function usk_per_unit_returns_correct_bracket_values(): void
    {
        $this->assertSame(18, FactoryProperties::uskPerUnit(1));
        $this->assertSame(15, FactoryProperties::uskPerUnit(5));
        $this->assertSame(12, FactoryProperties::uskPerUnit(50));
        $this->assertSame(9, FactoryProperties::uskPerUnit(500));
        $this->assertSame(6, FactoryProperties::uskPerUnit(5000));
        $this->assertSame(3, FactoryProperties::uskPerUnit(50000));
    }

    #[Test]
    public function fuel_per_turn_scales_with_tech_level(): void
    {
        $this->assertSame(0.5, FactoryProperties::fuelPerTurn(1));
        $this->assertSame(1.0, FactoryProperties::fuelPerTurn(2));
        $this->assertSame(2.5, FactoryProperties::fuelPerTurn(5));
    }

    #[Test]
    public function zero_units_returns_zero_labor(): void
    {
        $this->assertSame(0, FactoryProperties::proPerUnit(0));
        $this->assertSame(0, FactoryProperties::uskPerUnit(0));
    }
}
