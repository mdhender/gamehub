<?php

namespace Tests\Unit\Support;

use App\Support\FarmProperties;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FarmPropertiesTest extends TestCase
{
    #[Test]
    public function fuel_per_turn_returns_correct_values(): void
    {
        $this->assertSame(0.5, FarmProperties::fuelPerTurn(1));
        $this->assertSame(1.0, FarmProperties::fuelPerTurn(2));
        $this->assertSame(2.5, FarmProperties::fuelPerTurn(5));
        $this->assertSame(6.0, FarmProperties::fuelPerTurn(6));
        $this->assertSame(10.0, FarmProperties::fuelPerTurn(10));
    }

    #[Test]
    public function annual_output_returns_correct_values(): void
    {
        $this->assertSame(100, FarmProperties::annualOutput(1));
        $this->assertSame(40, FarmProperties::annualOutput(2));
        $this->assertSame(100, FarmProperties::annualOutput(5));
        $this->assertSame(200, FarmProperties::annualOutput(10));
    }

    #[Test]
    public function food_per_turn_is_annual_output_divided_by_four(): void
    {
        // TL-1: 100 / 4 = 25
        $this->assertSame(25.0, FarmProperties::foodPerTurn(1));
        // TL-2: 40 / 4 = 10
        $this->assertSame(10.0, FarmProperties::foodPerTurn(2));
        // TL-10: 200 / 4 = 50
        $this->assertSame(50.0, FarmProperties::foodPerTurn(10));
    }

    #[Test]
    public function unknown_tech_level_returns_zero(): void
    {
        $this->assertSame(0.0, FarmProperties::fuelPerTurn(0));
        $this->assertSame(0, FarmProperties::annualOutput(99));
    }
}
