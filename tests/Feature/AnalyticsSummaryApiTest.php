<?php

namespace Tests\Feature;

use App\Models\SpeedwayRace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AnalyticsSummaryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_zeroed_summary_when_there_is_no_data(): void
    {
        $response = $this->getJson('/api/analytics/summary');

        $response->assertOk()
            ->assertJsonPath('totals.races', 0)
            ->assertJsonPath('totals.validated_races', 0)
            ->assertJsonPath('totals.settled_races', 0)
            ->assertJsonPath('totals.pending_races', 0)
            ->assertJsonPath('favorite.wins', 0)
            ->assertJsonPath('favorite.losses', 0)
            ->assertJsonPath('favorite.win_rate', 0)
            ->assertJsonPath('favorite.theoretical_roi', 0)
            ->assertJsonPath('underdog.wins', 0)
            ->assertJsonPath('underdog.win_rate', 0)
            ->assertJsonPath('forecast.total', 0)
            ->assertJsonPath('forecast.hits', 0)
            ->assertJsonPath('forecast.hit_rate', 0)
            ->assertJsonPath('tricast.total', 0)
            ->assertJsonPath('tricast.hits', 0)
            ->assertJsonPath('tricast.hit_rate', 0)
            ->assertJsonPath('odds.average_winner_odd', 0)
            ->assertJsonPath('odds.average_favorite_odd', 0)
            ->assertJsonPath('odds.average_spread', 0)
            ->assertJsonPath('odds.average_house_margin', 0)
            ->assertJsonPath('metadata.forecast_applicable_count', 0)
            ->assertJsonPath('metadata.forecast_null_count', 0)
            ->assertJsonPath('metadata.tricast_applicable_count', 0)
            ->assertJsonPath('metadata.tricast_null_count', 0)
            ->assertJsonPath('metadata.house_margin_format', 'decimal_fraction_in_db_percentage_in_api')
            ->assertJsonPath('metadata.percentage_format', 'percentage_points_in_api');
    }

    public function test_applies_filters_and_computes_summary_metrics(): void
    {
        $baseDate = Carbon::parse('2026-06-17 15:00:00', 'UTC');

        SpeedwayRace::query()->create([
            'external_id' => 'race-summary-1',
            'status' => 'settled',
            'race_hour' => '12',
            'pilot_odds_raw' => '3.20|8.00|2.45|5.00',
            'winner_position' => 3,
            'winner_odd' => 2.45,
            'favorite_position' => 3,
            'favorite_odd' => 2.45,
            'underdog_position' => 2,
            'underdog_odd' => 8.00,
            'winner_was_favorite' => true,
            'winner_was_underdog' => false,
            'forecast_hit' => true,
            'tricast_winner_hit' => true,
            'tricast_exact_hit' => null,
            'tricast_hit' => null,
            'odds_spread' => 5.55,
            'house_margin' => 0.0456,
            'first_seen_at' => $baseDate,
            'raw_pending_payload' => ['Odds_Pilotos' => '3.20|8.00|2.45|5.00'],
        ]);

        SpeedwayRace::query()->create([
            'external_id' => 'race-summary-2',
            'status' => 'settled',
            'race_hour' => '13',
            'pilot_odds_raw' => '2.10|4.00|4.50|6.00',
            'winner_position' => 4,
            'winner_odd' => 6.00,
            'favorite_position' => 1,
            'favorite_odd' => 2.10,
            'underdog_position' => 4,
            'underdog_odd' => 6.00,
            'winner_was_favorite' => false,
            'winner_was_underdog' => true,
            'forecast_hit' => false,
            'tricast_winner_hit' => false,
            'tricast_exact_hit' => null,
            'tricast_hit' => null,
            'odds_spread' => 3.90,
            'house_margin' => 0.0952,
            'first_seen_at' => $baseDate->copy()->addHour(),
            'raw_pending_payload' => null,
        ]);

        SpeedwayRace::query()->create([
            'external_id' => 'race-summary-pending',
            'status' => 'pending',
            'race_hour' => '12',
            'pilot_odds_raw' => '2.00|3.00|4.00|5.00',
            'first_seen_at' => $baseDate,
        ]);

        $response = $this->getJson('/api/analytics/summary?date_from=2026-06-17&date_to=2026-06-17&hour_from=12&hour_to=12&only_validated=1');

        $response->assertOk()
            ->assertJsonPath('filters.only_validated', true)
            ->assertJsonPath('totals.races', 1)
            ->assertJsonPath('totals.validated_races', 1)
            ->assertJsonPath('totals.settled_races', 1)
            ->assertJsonPath('totals.pending_races', 1)
            ->assertJsonPath('favorite.wins', 1)
            ->assertJsonPath('favorite.losses', 0)
            ->assertJsonPath('favorite.win_rate', 100)
            ->assertJsonPath('favorite.theoretical_roi', 145)
            ->assertJsonPath('underdog.wins', 0)
            ->assertJsonPath('underdog.win_rate', 0)
            ->assertJsonPath('forecast.total', 1)
            ->assertJsonPath('forecast.hits', 1)
            ->assertJsonPath('forecast.hit_rate', 100)
            ->assertJsonPath('tricast.total', 0)
            ->assertJsonPath('tricast.hits', 0)
            ->assertJsonPath('tricast.hit_rate', 0)
            ->assertJsonPath('odds.average_winner_odd', 2.45)
            ->assertJsonPath('odds.average_favorite_odd', 2.45)
            ->assertJsonPath('odds.average_spread', 5.55)
            ->assertJsonPath('odds.average_house_margin', 4.56)
            ->assertJsonPath('metadata.forecast_applicable_count', 1)
            ->assertJsonPath('metadata.forecast_null_count', 0)
            ->assertJsonPath('metadata.tricast_applicable_count', 0)
            ->assertJsonPath('metadata.tricast_null_count', 1)
            ->assertJsonPath('metadata.percentage_format', 'percentage_points_in_api');
    }

    public function test_forecast_and_tricast_hit_rates_use_market_rank_vs_result_from_payload(): void
    {
        $baseDate = Carbon::parse('2026-06-17 15:00:00', 'UTC');

        SpeedwayRace::query()->create([
            'external_id' => 'race-analytics-forecast-hit',
            'status' => 'settled',
            'pilot_odds_raw' => '3.20|8.00|2.45|5.00',
            'winner_position' => 3,
            'first_seen_at' => $baseDate,
            'settled_at' => $baseDate,
            'raw_pending_payload' => ['Odds_Pilotos' => '3.20|8.00|2.45|5.00'],
            'raw_result_payload' => [
                'Previsao' => '3-1',
                'Previsao_Tricast' => '3-1-4',
            ],
        ]);

        SpeedwayRace::query()->create([
            'external_id' => 'race-analytics-forecast-miss',
            'status' => 'settled',
            'pilot_odds_raw' => '3.20|8.00|2.45|5.00',
            'winner_position' => 3,
            'first_seen_at' => $baseDate,
            'settled_at' => $baseDate,
            'raw_pending_payload' => ['Odds_Pilotos' => '3.20|8.00|2.45|5.00'],
            'raw_result_payload' => [
                'Previsao' => '3-2',
                'Previsao_Tricast' => '3-2-4',
            ],
        ]);

        $this->artisan('speedway:recalculate-metrics', ['--chunk' => 10])
            ->assertExitCode(0);

        $response = $this->getJson('/api/analytics/summary?date_from=2026-06-17&date_to=2026-06-17');

        $response->assertOk()
            ->assertJsonPath('forecast.total', 2)
            ->assertJsonPath('forecast.hits', 1)
            ->assertJsonPath('forecast.hit_rate', 50)
            ->assertJsonPath('tricast.total', 2)
            ->assertJsonPath('tricast.hits', 1)
            ->assertJsonPath('tricast.hit_rate', 50);
    }

    public function test_returns_distribution_by_favorite_odd_band(): void
    {
        SpeedwayRace::query()->create([
            'external_id' => 'race-dist-1',
            'status' => 'settled',
            'favorite_odd' => 1.95,
            'winner_was_favorite' => true,
            'first_seen_at' => now(),
            'raw_pending_payload' => ['Odds_Pilotos' => '1.95|3.10|4.20|8.00'],
        ]);

        SpeedwayRace::query()->create([
            'external_id' => 'race-dist-2',
            'status' => 'settled',
            'favorite_odd' => 2.45,
            'winner_was_favorite' => false,
            'first_seen_at' => now(),
            'raw_pending_payload' => ['Odds_Pilotos' => '2.45|2.90|4.10|7.20'],
        ]);

        $response = $this->getJson('/api/analytics/distributions?only_validated=1');

        $response->assertOk()
            ->assertJsonPath('filters.only_validated', true)
            ->assertJsonCount(4, 'favorite_odd_bands')
            ->assertJsonPath('favorite_odd_bands.0.key', 'lt_2')
            ->assertJsonPath('favorite_odd_bands.0.races', 1)
            ->assertJsonPath('favorite_odd_bands.0.wins', 1)
            ->assertJsonPath('favorite_odd_bands.0.win_rate', 100)
            ->assertJsonPath('favorite_odd_bands.1.key', 'from_2_to_3')
            ->assertJsonPath('favorite_odd_bands.1.races', 1)
            ->assertJsonPath('favorite_odd_bands.1.wins', 0)
            ->assertJsonPath('favorite_odd_bands.1.win_rate', 0);
    }
}
