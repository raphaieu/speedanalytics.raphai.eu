<?php

namespace App\Enums\Demo;

enum DemoOperationStatus: string
{
    case Open = 'open';
    case Settled = 'settled';
    case Cancelled = 'cancelled';
}
