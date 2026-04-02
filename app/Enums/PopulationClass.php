<?php

namespace App\Enums;

enum PopulationClass: string
{
    case Unemployable = 'UEM';
    case Unskilled = 'USK';
    case Professional = 'PRO';
    case Soldier = 'SLD';
    case ConstructionWorker = 'CNW';
    case Spy = 'SPY';
    case Police = 'PLC';
    case SpecialAgent = 'SAG';
    case Trainee = 'TRN';
}
