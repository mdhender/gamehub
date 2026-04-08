<?php

namespace App\Enums;

enum ColonyKind: string
{
    case OpenSurface = 'COPN';
    case Enclosed = 'CENC';
    case Orbital = 'CORB';
    case Ship = 'CSHP';

    public function vuFactor(): int
    {
        return match ($this) {
            self::OpenSurface => 1,
            self::Enclosed => 5,
            self::Orbital, self::Ship => 10,
        };
    }
}
