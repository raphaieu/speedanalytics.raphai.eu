<?php

namespace App\Enums\Demo;

enum DemoOperationResult: string
{
    case Pending = 'pending';
    case Win = 'win';
    case Loss = 'loss';
    case Void = 'void';
}
