<?php

namespace App\Enums;

enum TurnStatus: string
{
    case Pending = 'pending';
    case Generating = 'generating';
    case Completed = 'completed';
    case Closed = 'closed';
}
