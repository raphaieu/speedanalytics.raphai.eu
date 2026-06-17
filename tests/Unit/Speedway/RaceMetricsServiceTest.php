<?php

namespace Tests\Unit\Speedway;

use App\Services\Speedway\RaceMetricsService;
use PHPUnit\Framework\TestCase;

class RaceMetricsServiceTest extends TestCase
{
    public function test_calculates_favorite_underdog_spread_and_house_margin(): void
    {
        $service = new RaceMetricsService;

        $metrics = $service->calculate([
            'pilot_odds_raw' => '3.20|8.00|2.45|5.00',
            'winner_position' => 3,
            'raw_result_payload' => [
                'Previsao' => '3-1',
                'Previsao_Tricast' => '3-1-4',
            ],
        ]);

        $this->assertSame(3, $metrics['favorite_position']);
        $this->assertEquals(2.45, $metrics['favorite_odd']);
        $this->assertSame(1, $metrics['second_favorite_position']);
        $this->assertEquals(3.20, $metrics['second_favorite_odd']);
        $this->assertSame(2, $metrics['underdog_position']);
        $this->assertEquals(8.00, $metrics['underdog_odd']);
        $this->assertEquals(5.55, $metrics['odds_spread']);
        $this->assertEqualsWithDelta(0.0456632653, (float) $metrics['house_margin'], 0.000001);
        $this->assertTrue($metrics['winner_was_favorite']);
        $this->assertFalse($metrics['winner_was_underdog']);
        $this->assertSame(1, $metrics['winner_odd_rank']);
        $this->assertTrue($metrics['forecast_hit']);
        $this->assertTrue($metrics['tricast_winner_hit']);
        $this->assertTrue($metrics['tricast_exact_hit']);
        $this->assertTrue($metrics['tricast_hit']);
    }

    public function test_marks_winner_as_underdog_when_applicable(): void
    {
        $service = new RaceMetricsService;

        $metrics = $service->calculate([
            'pilot_odds_raw' => '3.20|8.00|2.45|5.00',
            'winner_position' => 2,
            'raw_result_payload' => [
                'Previsao' => '2-1',
                'Previsao_Tricast' => '2-1-3',
            ],
        ]);

        $this->assertFalse($metrics['winner_was_favorite']);
        $this->assertTrue($metrics['winner_was_underdog']);
        $this->assertSame(4, $metrics['winner_odd_rank']);
        $this->assertFalse($metrics['forecast_hit']);
        $this->assertFalse($metrics['tricast_winner_hit']);
        $this->assertFalse($metrics['tricast_exact_hit']);
        $this->assertFalse($metrics['tricast_hit']);
    }

    public function test_breaks_tied_odds_by_lowest_position(): void
    {
        $service = new RaceMetricsService;

        $metrics = $service->calculate([
            'pilot_odds_raw' => '2.00|2.00|8.00|8.00',
            'winner_position' => 1,
            'prediction' => '1-2',
            'tricast_prediction' => '1-2-3',
        ]);

        $this->assertSame(1, $metrics['favorite_position']);
        $this->assertSame(2.0, $metrics['favorite_odd']);
        $this->assertSame(2, $metrics['second_favorite_position']);
        $this->assertSame(3, $metrics['underdog_position']);
        $this->assertSame(8.0, $metrics['underdog_odd']);
        $this->assertSame(1, $metrics['winner_odd_rank']);
    }

    public function test_calculates_winner_odd_rank_for_non_favorite_non_underdog_winner(): void
    {
        $service = new RaceMetricsService;

        $metrics = $service->calculate([
            'pilot_odds_raw' => '3.20|8.00|2.45|5.00',
            'winner_position' => 1,
        ]);

        $this->assertFalse($metrics['winner_was_favorite']);
        $this->assertFalse($metrics['winner_was_underdog']);
        $this->assertSame(2, $metrics['winner_odd_rank']);
    }

    public function test_forecast_hit_true_when_first_and_second_favorites_match_real_order(): void
    {
        $service = new RaceMetricsService;

        $metrics = $service->calculate([
            'pilot_odds_raw' => '3.20|8.00|2.45|5.00',
            'winner_position' => 3,
            'raw_result_payload' => [
                'Previsao' => '3-1',
            ],
        ]);

        $this->assertTrue($metrics['forecast_hit']);
    }

    public function test_forecast_hit_false_when_first_and_second_favorites_do_not_match_real_order(): void
    {
        $service = new RaceMetricsService;

        $metrics = $service->calculate([
            'pilot_odds_raw' => '3.20|8.00|2.45|5.00',
            'winner_position' => 3,
            'raw_result_payload' => [
                'Previsao' => '3-2',
            ],
        ]);

        $this->assertFalse($metrics['forecast_hit']);
    }

    public function test_forecast_hit_is_null_when_no_valid_forecast_data_exists(): void
    {
        $service = new RaceMetricsService;

        $metrics = $service->calculate([
            'pilot_odds_raw' => null,
            'winner_position' => 1,
            'raw_result_payload' => [
                'Previsao' => null,
            ],
        ]);

        $this->assertNull($metrics['forecast_hit']);
    }

    public function test_tricast_exact_hit_is_null_without_full_real_order(): void
    {
        $service = new RaceMetricsService;

        $metrics = $service->calculate([
            'pilot_odds_raw' => '2.45|3.10|6.00|9.00',
            'winner_position' => 1,
            'raw_result_payload' => [
                'Vencedor' => 1,
            ],
        ]);

        $this->assertTrue($metrics['tricast_winner_hit']);
        $this->assertNull($metrics['tricast_exact_hit']);
    }
}
