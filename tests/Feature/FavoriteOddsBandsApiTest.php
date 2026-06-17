<?php

namespace Tests\Feature;

use App\Models\SpeedwayRace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FavoriteOddsBandsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_all_bands_and_calculated_metrics(): void
    {
        $baseDate = Carbon::parse('2026-06-17 12:00:00', 'UTC');

        SpeedwayRace::query()->create([
            'external_id' => 'band-1',
            'status' => 'settled',
            'race_hour' => '12',
            'favorite_odd' => 1.50,
            'winner_was_favorite' => true,
            'first_seen_at' => $baseDate,
            'raw_pending_payload' => ['Odds_Pilotos' => '1.50|3.00|5.00|8.00'],
        ]);

        SpeedwayRace::query()->create([
            'external_id' => 'band-2',
            'status' => 'settled',
            'race_hour' => '12',
            'favorite_odd' => 2.50,
            'winner_was_favorite' => true,
            'first_seen_at' => $baseDate->copy()->addMinute(),
            'raw_pending_payload' => ['Odds_Pilotos' => '2.50|2.80|4.50|7.00'],
        ]);

        SpeedwayRace::query()->create([
            'external_id' => 'band-3',
            'status' => 'settled',
            'race_hour' => '12',
            'favorite_odd' => 2.80,
            'winner_was_favorite' => false,
            'first_seen_at' => $baseDate->copy()->addMinutes(2),
            'raw_pending_payload' => ['Odds_Pilotos' => '2.80|3.10|4.60|6.50'],
        ]);

        // Não validada (não deve entrar no padrão only_validated=true)
        SpeedwayRace::query()->create([
            'external_id' => 'band-unvalidated',
            'status' => 'settled',
            'race_hour' => '12',
            'favorite_odd' => 1.70,
            'winner_was_favorite' => false,
            'first_seen_at' => null,
            'raw_pending_payload' => null,
        ]);

        $response = $this->getJson('/api/analytics/favorite-odds-bands?date_from=2026-06-17&date_to=2026-06-17&hour_from=12&hour_to=12');

        $response->assertOk()
            ->assertJsonPath('filters.only_validated', true)
            ->assertJsonCount(7, 'bands')
            ->assertJsonPath('summary.total_races', 3)
            ->assertJsonPath('summary.profitable_bands', 2)
            ->assertJsonPath('summary.best_band.band', '1.00-1.99')
            ->assertJsonPath('summary.best_band.theoretical_roi', 50)
            ->assertJsonPath('summary.worst_band.band', '2.50-2.99')
            ->assertJsonPath('summary.worst_band.theoretical_roi', 25)
            ->assertJsonPath('metadata.percentage_format', 'percentage_points_in_api')
            ->assertJsonPath('bands.0.band', '1.00-1.99')
            ->assertJsonPath('bands.0.total', 1)
            ->assertJsonPath('bands.0.wins', 1)
            ->assertJsonPath('bands.0.losses', 0)
            ->assertJsonPath('bands.0.win_rate', 100)
            ->assertJsonPath('bands.0.implied_probability', 66.67)
            ->assertJsonPath('bands.0.edge_vs_implied', 33.33)
            ->assertJsonPath('bands.0.profit_loss', 0.5)
            ->assertJsonPath('bands.0.theoretical_roi', 50)
            ->assertJsonPath('bands.2.band', '2.50-2.99')
            ->assertJsonPath('bands.2.total', 2)
            ->assertJsonPath('bands.2.wins', 1)
            ->assertJsonPath('bands.2.losses', 1)
            ->assertJsonPath('bands.2.win_rate', 50)
            ->assertJsonPath('bands.2.average_favorite_odd', 2.65)
            ->assertJsonPath('bands.2.implied_probability', 37.86)
            ->assertJsonPath('bands.2.edge_vs_implied', 12.14)
            ->assertJsonPath('bands.2.profit_loss', 0.5)
            ->assertJsonPath('bands.2.theoretical_roi', 25);
    }

    public function test_can_include_non_validated_races_when_requested(): void
    {
        SpeedwayRace::query()->create([
            'external_id' => 'band-v',
            'status' => 'settled',
            'favorite_odd' => 1.80,
            'winner_was_favorite' => true,
            'first_seen_at' => now(),
            'raw_pending_payload' => ['Odds_Pilotos' => '1.80|2.40|4.20|7.00'],
        ]);

        SpeedwayRace::query()->create([
            'external_id' => 'band-nv',
            'status' => 'settled',
            'favorite_odd' => 1.90,
            'winner_was_favorite' => false,
            'first_seen_at' => null,
            'raw_pending_payload' => null,
        ]);

        $response = $this->getJson('/api/analytics/favorite-odds-bands?only_validated=0');

        $response->assertOk()
            ->assertJsonPath('filters.only_validated', false)
            ->assertJsonPath('summary.total_races', 2)
            ->assertJsonPath('bands.0.total', 2)
            ->assertJsonPath('bands.0.wins', 1)
            ->assertJsonPath('bands.0.losses', 1);
    }
}
