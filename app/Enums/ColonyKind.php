<?php

namespace App\Enums;

enum ColonyKind: string
{
    case OpenSurface = 'COPN';
    case Enclosed = 'CENC';
    case Orbital = 'CORB';
}
