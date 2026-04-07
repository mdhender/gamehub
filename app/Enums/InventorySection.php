<?php

namespace App\Enums;

enum InventorySection: string
{
    case SuperStructure = 'super_structure';
    case Structure = 'structure';
    case Operational = 'operational';
    case Cargo = 'cargo';
}
