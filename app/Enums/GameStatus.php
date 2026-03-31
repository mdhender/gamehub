<?php

namespace App\Enums;

enum GameStatus: string
{
    case Setup = 'setup';
    case StarsGenerated = 'stars_generated';
    case PlanetsGenerated = 'planets_generated';
    case DepositsGenerated = 'deposits_generated';
    case HomeSystemGenerated = 'home_system_generated';
    case Active = 'active';
}
