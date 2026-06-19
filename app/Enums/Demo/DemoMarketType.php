<?php

namespace App\Enums\Demo;

enum DemoMarketType: string
{
    case Winner = 'winner';
    case Forecast = 'forecast';
    case Tricast = 'tricast';
}
