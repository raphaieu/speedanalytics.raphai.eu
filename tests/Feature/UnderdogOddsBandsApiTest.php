<?php

namespace Tests\Feature;

use App\Models\SpeedwayRace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class UnderdogOddsBandsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_all_underdog_bands_and_metrics(): void
    {
        $baseDate = Carbon::parse('2026-06-17 12:00:00', 'UTC');

        SpeedwayRace::query()->create([
            'external_id' => 'ud-band-1',
            'status' => 'settled',
            'race_hour' => '12',
            'underdog_odd' => 8.00,
            'winner_was_underdog' => true,
            'first_seen_at' => $baseDate,
            'raw_pending_payload' => ['Odds_Pilotos' => '2.40|8.00|3.50|5.20'],
        ]);

        SpeedwayRace::query()->create([
            'external_id' => 'ud-band-2',
            'status' => 'settled',
            'race_hour' => '12',
            'underdog_odd' => 8.50,
            'winner_was_underdog' => false,
            'first_seen_at' => $baseDate->copy()->addMinute(),
            'raw_pending_payload' => ['Odds_Pilotos' => '2.30|8.50|3.70|4.90'],
        ]);

        SpeedwayRace::query()->create([
            'external_id' => 'ud-band-3',
            'status' => 'settled',
            'race_hour' => '12',
            'underdog_odd' => 10.50,
            'winner_was_underdog' => false,
            'first_seen_at' => $baseDate->copy()->addMinutes(2),
            'raw_pending_payload' => ['Odds_Pilotos' => '2.10|3.90|4.20|10.50'],
        ]);

        $response = $this->getJson('/api/analytics/underdog-odds-bands?date_from=2026-06-17&date_to=2026-06-17&hour_from=12&hour_to=12');

        $response->assertOk()
            ->assertJsonPath('filters.only_validated', true)
            ->assertJsonCount(6, 'bands')
            ->assertJsonPath('summary.total_races', 3)
            ->assertJsonPath('summary.profitable_bands', 1)
            ->assertJsonPath('summary.best_band.band', '8.00-9.99')
            ->assertJsonPath('summary.best_band.theoretical_roi', 300)
            ->assertJsonPath('summary.worst_band.band', '10.00-11.99')
            ->assertJsonPath('summary.worst_band.theoretical_roi', -100)
            ->assertJsonPath('bands.2.band', '8.00-9.99')
            ->assertJsonPath('bands.2.total', 2)
            ->assertJsonPath('bands.2.wins', 1)
            ->assertJsonPath('bands.2.losses', 1)
            ->assertJsonPath('bands.2.win_rate', 50)
            ->assertJsonPath('bands.2.average_underdog_odd', 8.25)
            ->assertJsonPath('bands.2.implied_probability', 12.13)
            ->assertJsonPath('bands.2.edge_vs_implied', 37.87)
            ->assertJsonPath('bands.2.profit_loss', 6)
            ->assertJsonPath('bands.2.theoretical_roi', 300)
            ->assertJsonPath('bands.3.band', '10.00-11.99')
            ->assertJsonPath('bands.3.total', 1)
            ->assertJsonPath('bands.3.wins', 0)
            ->assertJsonPath('bands.3.losses', 1)
            ->assertJsonPath('bands.3.theoretical_roi', -100)
            ->assertJsonPath('bands.5.band', '15.00+')
            ->assertJsonPath('bands.5.total', 0);
    }
}
