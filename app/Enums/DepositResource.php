<?php

namespace App\Enums;

enum DepositResource: string
{
    case Gold = 'GOLD';
    case Fuel = 'FUEL';
    case Metallics = 'METS';
    case NonMetallics = 'NMTS';
}
