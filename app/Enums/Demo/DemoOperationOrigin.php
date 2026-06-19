<?php

namespace App\Enums\Demo;

enum DemoOperationOrigin: string
{
    case Manual = 'manual';
    case Strategy = 'strategy';
}
