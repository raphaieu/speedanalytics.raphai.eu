<?php

namespace Tests\Unit\Speedway;

use App\Models\SpeedwayRace;
use App\Services\MarketOddEstimatorService;
use Tests\TestCase;

class MarketOddEstimatorServiceTest extends TestCase
{
    private MarketOddEstimatorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MarketOddEstimatorService;
    }

    public function test_estimates_forecast_odd_with_product_multiplier(): void
    {
        $race = new SpeedwayRace([
            'pilot_odds_raw' => '3.20|8.00|2.45|5.00',
        ]);

        $estimated = $this->service->estimateForecastOdd($race, '3-1');

        $this->assertEquals(5.10, $estimated);
    }

    public function test_estimates_tricast_odd_with_product_multiplier(): void
    {
        $race = new SpeedwayRace([
            'pilot_odds_raw' => '3.20|8.00|2.45|5.00',
        ]);

        $estimated = $this->service->estimateTricastOdd($race, '3-1-4');

        $this->assertEquals(13.72, $estimated);
    }

    public function test_returns_null_for_invalid_order(): void
    {
        $race = new SpeedwayRace([
            'pilot_odds_raw' => '3.20|8.00|2.45|5.00',
        ]);

        $this->assertNull($this->service->estimateForecastOdd($race, ''));
        $this->assertNull($this->service->estimateTricastOdd($race, '3-1'));
    }

    public function test_returns_null_when_race_has_insufficient_odds(): void
    {
        $race = new SpeedwayRace([
            'pilot_odds_raw' => '3.20|8.00',
        ]);

        $this->assertNull($this->service->estimateForecastOdd($race, '3-1'));
        $this->assertNull($this->service->estimateTricastOdd($race, '3-1-4'));
    }
}
