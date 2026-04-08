<?php

namespace App\Support;

use App\Enums\UnitCode;

class UnitProperties
{
    /**
     * Volume per single unit at the given tech level (in VU).
     */
    public static function volumePerUnit(UnitCode $code, int $techLevel): float
    {
        return match ($code) {
            UnitCode::Structure => 0.5,
            UnitCode::LightStructure => 0.05,
            UnitCode::Factories => 12 + (2 * $techLevel),
            UnitCode::Farms => 6 + $techLevel,
            UnitCode::Mines => 10 + (2 * $techLevel),
            UnitCode::Sensors => 2998 + (2 * $techLevel),
            UnitCode::LifeSupports => 8 * $techLevel,
            UnitCode::Automation,
            UnitCode::AntiMissiles,
            UnitCode::AssaultCraft,
            UnitCode::Missiles,
            UnitCode::Transports => 4 * $techLevel,
            UnitCode::EnergyShields => 50 * $techLevel,
            UnitCode::EnergyWeapons => 10 * $techLevel,
            UnitCode::HyperEngines => 45 * $techLevel,
            UnitCode::MissileLaunchers,
            UnitCode::SpaceDrives => 25 * $techLevel,
            UnitCode::MilitaryRobots => (2 * $techLevel) + 20,
            UnitCode::AssaultWeapons => 20,
            UnitCode::ConsumerGoods => 0.6,
            UnitCode::Food => 6,
            UnitCode::Fuel,
            UnitCode::Gold,
            UnitCode::Metals,
            UnitCode::NonMetals => 1,
            UnitCode::MilitarySupplies => 0.04,
            UnitCode::Research,
            UnitCode::RobotProbes,
            UnitCode::PowerPlants,
            UnitCode::Laboratories => 0,
        };
    }

    /**
     * Mass per single unit at the given tech level (in MU).
     *
     * Currently identical to volume for all units.
     */
    public static function massPerUnit(UnitCode $code, int $techLevel): float
    {
        return self::volumePerUnit($code, $techLevel);
    }
}
