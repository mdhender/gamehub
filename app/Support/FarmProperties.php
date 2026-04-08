<?php

namespace App\Support;

class FarmProperties
{
    /** @var array<int, float> */
    private const FUEL_PER_TURN = [
        1 => 0.5,
        2 => 1.0,
        3 => 1.5,
        4 => 2.0,
        5 => 2.5,
        6 => 6.0,
        7 => 7.0,
        8 => 8.0,
        9 => 9.0,
        10 => 10.0,
    ];

    /** @var array<int, int> */
    private const ANNUAL_OUTPUT = [
        1 => 100,
        2 => 40,
        3 => 60,
        4 => 80,
        5 => 100,
        6 => 120,
        7 => 140,
        8 => 160,
        9 => 180,
        10 => 200,
    ];

    /**
     * Fuel consumed per farm unit per turn.
     */
    public static function fuelPerTurn(int $techLevel): float
    {
        return self::FUEL_PER_TURN[$techLevel] ?? 0;
    }

    /**
     * Annual FOOD output per farm unit.
     */
    public static function annualOutput(int $techLevel): int
    {
        return self::ANNUAL_OUTPUT[$techLevel] ?? 0;
    }

    /**
     * FOOD produced per farm unit per turn (annual output / 4).
     */
    public static function foodPerTurn(int $techLevel): float
    {
        return self::annualOutput($techLevel) / 4;
    }
}
