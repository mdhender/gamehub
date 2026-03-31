<?php

namespace App\Enums;

enum DepositResource: string
{
    case Gold = 'gold';
    case Fuel = 'fuel';
    case Metallics = 'metallics';
    case NonMetallics = 'non_metallics';
}
