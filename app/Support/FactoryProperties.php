<?php

namespace App\Support;

class FactoryProperties
{
    /**
     * @var list<array{min: int, max: int, pro: int, usk: int}>
     */
    private const LABOR_BRACKETS = [
        ['min' => 50000, 'max' => PHP_INT_MAX, 'pro' => 1, 'usk' => 3],
        ['min' => 5000, 'max' => 49999, 'pro' => 2, 'usk' => 6],
        ['min' => 500, 'max' => 4999, 'pro' => 3, 'usk' => 9],
        ['min' => 50, 'max' => 499, 'pro' => 4, 'usk' => 12],
        ['min' => 5, 'max' => 49, 'pro' => 5, 'usk' => 15],
        ['min' => 1, 'max' => 4, 'pro' => 6, 'usk' => 18],
    ];

    /**
     * Professionals required per factory unit, based on group size.
     */
    public static function proPerUnit(int $totalUnits): int
    {
        foreach (self::LABOR_BRACKETS as $bracket) {
            if ($totalUnits >= $bracket['min']) {
                return $bracket['pro'];
            }
        }

        return 0;
    }

    /**
     * Unskilled workers required per factory unit, based on group size.
     */
    public static function uskPerUnit(int $totalUnits): int
    {
        foreach (self::LABOR_BRACKETS as $bracket) {
            if ($totalUnits >= $bracket['min']) {
                return $bracket['usk'];
            }
        }

        return 0;
    }

    /**
     * Fuel consumed per factory unit per turn.
     */
    public static function fuelPerTurn(int $techLevel): float
    {
        return $techLevel * 0.5;
    }
}
