<?php

namespace Tests\Unit\Support;

use App\Enums\UnitCode;
use App\Support\UnitProperties;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class UnitPropertiesTest extends TestCase
{
    #[Test]
    public function structure_units_have_fixed_volume(): void
    {
        $this->assertSame(0.5, UnitProperties::volumePerUnit(UnitCode::Structure, 1));
        $this->assertSame(0.5, UnitProperties::volumePerUnit(UnitCode::Structure, 5));
    }

    #[Test]
    public function light_structure_has_fixed_volume(): void
    {
        $this->assertSame(0.05, UnitProperties::volumePerUnit(UnitCode::LightStructure, 1));
    }

    #[Test]
    public function factories_use_offset_plus_linear_formula(): void
    {
        // FCT: 12 + (2 × TL)
        $this->assertSame(14.0, UnitProperties::volumePerUnit(UnitCode::Factories, 1));
        $this->assertSame(16.0, UnitProperties::volumePerUnit(UnitCode::Factories, 2));
        $this->assertSame(22.0, UnitProperties::volumePerUnit(UnitCode::Factories, 5));
    }

    #[Test]
    public function farms_use_offset_plus_tech_level(): void
    {
        // FRM: 6 + TL
        $this->assertSame(7.0, UnitProperties::volumePerUnit(UnitCode::Farms, 1));
        $this->assertSame(11.0, UnitProperties::volumePerUnit(UnitCode::Farms, 5));
    }

    #[Test]
    public function mines_use_offset_plus_linear_formula(): void
    {
        // MIN: 10 + (2 × TL)
        $this->assertSame(12.0, UnitProperties::volumePerUnit(UnitCode::Mines, 1));
        $this->assertSame(20.0, UnitProperties::volumePerUnit(UnitCode::Mines, 5));
    }

    #[Test]
    public function sensors_use_offset_plus_linear_formula(): void
    {
        // SEN: 2998 + (2 × TL)
        $this->assertSame(3000.0, UnitProperties::volumePerUnit(UnitCode::Sensors, 1));
    }

    #[Test]
    public function automation_scales_with_tech_level(): void
    {
        // AUT: 4 × TL
        $this->assertSame(4.0, UnitProperties::volumePerUnit(UnitCode::Automation, 1));
        $this->assertSame(20.0, UnitProperties::volumePerUnit(UnitCode::Automation, 5));
    }

    #[Test]
    public function transports_scale_with_tech_level(): void
    {
        // TPT: 4 × TL
        $this->assertSame(4.0, UnitProperties::volumePerUnit(UnitCode::Transports, 1));
    }

    #[Test]
    public function consumables_have_fixed_values(): void
    {
        $this->assertSame(0.6, UnitProperties::volumePerUnit(UnitCode::ConsumerGoods, 1));
        $this->assertSame(6.0, UnitProperties::volumePerUnit(UnitCode::Food, 1));
        $this->assertSame(1.0, UnitProperties::volumePerUnit(UnitCode::Fuel, 1));
        $this->assertSame(1.0, UnitProperties::volumePerUnit(UnitCode::Gold, 1));
        $this->assertSame(1.0, UnitProperties::volumePerUnit(UnitCode::Metals, 1));
        $this->assertSame(1.0, UnitProperties::volumePerUnit(UnitCode::NonMetals, 1));
        $this->assertSame(0.04, UnitProperties::volumePerUnit(UnitCode::MilitarySupplies, 1));
    }

    #[Test]
    public function unspecified_units_return_zero(): void
    {
        $this->assertSame(0.0, UnitProperties::volumePerUnit(UnitCode::Research, 1));
        $this->assertSame(0.0, UnitProperties::volumePerUnit(UnitCode::RobotProbes, 1));
        $this->assertSame(0.0, UnitProperties::volumePerUnit(UnitCode::PowerPlants, 1));
    }

    #[Test]
    public function mass_equals_volume_for_all_units(): void
    {
        foreach (UnitCode::cases() as $code) {
            $this->assertSame(
                UnitProperties::volumePerUnit($code, 1),
                UnitProperties::massPerUnit($code, 1),
                "Mass should equal volume for {$code->value}"
            );
        }
    }

    #[Test]
    public function military_robots_use_offset_plus_linear(): void
    {
        // MTBT: (2 × TL) + 20
        $this->assertSame(22.0, UnitProperties::volumePerUnit(UnitCode::MilitaryRobots, 1));
        $this->assertSame(30.0, UnitProperties::volumePerUnit(UnitCode::MilitaryRobots, 5));
    }

    #[Test]
    public function energy_shields_scale_at_fifty_times_tech_level(): void
    {
        // ESH: 50 × TL
        $this->assertSame(50.0, UnitProperties::volumePerUnit(UnitCode::EnergyShields, 1));
        $this->assertSame(150.0, UnitProperties::volumePerUnit(UnitCode::EnergyShields, 3));
    }

    #[Test]
    public function life_support_scales_at_eight_times_tech_level(): void
    {
        // LFS: 8 × TL
        $this->assertSame(8.0, UnitProperties::volumePerUnit(UnitCode::LifeSupports, 1));
        $this->assertSame(40.0, UnitProperties::volumePerUnit(UnitCode::LifeSupports, 5));
    }
}
