<?php

namespace App\Enums;

enum PlanetType: string
{
    case Terrestrial = 'terrestrial';
    case Asteroid = 'asteroid';
    case GasGiant = 'gas_giant';
}
