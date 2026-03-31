<?php

namespace App\Enums;

enum GenerationStepName: string
{
    case Stars = 'stars';
    case Planets = 'planets';
    case Deposits = 'deposits';
    case HomeSystem = 'home_system';
}
