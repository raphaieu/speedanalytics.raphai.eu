<?php

namespace Tests\Feature\Speedway;

use App\Models\SpeedwayPayload;
use App\Models\SpeedwayRace;
use App\Services\Speedway\RaceTimingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PendingRaceReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_races_api_excludes_stale_race(): void
    {
        $timezone = RaceTimingService::TIMEZONE;
        $now = now($timezone);
        $timingService = app(RaceTimingService::class);

        $actionableBr = $now->copy()->addMinutes(5);
        $staleBr = $now->copy()->subMinutes(20);

        SpeedwayRace::query()->create(array_merge([
            'external_id' => 'actionable-race',
            'status' => 'pending',
            'pilot_odds_raw' => '2.00|3.00|4.00|5.00',
            'first_seen_at' => $now->copy()->utc(),
        ], $this->virtualSlotFromBrazil($timingService, $actionableBr)));

        SpeedwayRace::query()->create(array_merge([
            'external_id' => 'stale-race',
            'status' => 'pending',
            'pilot_odds_raw' => '2.00|3.00|4.00|5.00',
            'first_seen_at' => $now->copy()->utc(),
        ], $this->virtualSlotFromBrazil($timingService, $staleBr)));

        $this->getJson('/api/demo/pending-races')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.external_id', 'actionable-race')
            ->assertJsonPath('data.0.is_stale', false)
            ->assertJsonPath('meta.stale_pending', 1)
            ->assertJsonPath('meta.actionable', 1);
    }

    public function test_stuck_pending_from_previous_day_is_excluded_even_with_evening_slot(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-22 20:00:00', RaceTimingService::TIMEZONE));

        SpeedwayRace::query()->create([
            'external_id' => '911365',
            'status' => 'pending',
            'race_hour' => '23',
            'race_minute' => '37',
            'pilot_odds_raw' => '3.00|2.75|5.25|4.00',
            'first_seen_at' => Carbon::parse('2026-06-21 23:30:00', RaceTimingService::TIMEZONE)->utc(),
        ]);

        SpeedwayRace::query()->create([
            'external_id' => '911438',
            'status' => 'pending',
            'race_hour' => '3',
            'race_minute' => '16',
            'pilot_odds_raw' => '3.00|2.75|5.25|4.00',
            'first_seen_at' => Carbon::parse('2026-06-22 03:10:00', RaceTimingService::TIMEZONE)->utc(),
        ]);

        SpeedwayRace::query()->create([
            'external_id' => '912803',
            'status' => 'pending',
            'race_hour' => '0',
            'race_minute' => '31',
            'pilot_odds_raw' => '3.00|2.75|5.25|4.00',
            'first_seen_at' => Carbon::parse('2026-06-22 19:55:00', RaceTimingService::TIMEZONE)->utc(),
        ]);

        $this->getJson('/api/demo/pending-races')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.external_id', '912803')
            ->assertJsonPath('data.0.schedule_time_label', '00:31')
            ->assertJsonPath('data.0.starts_at_br_label', '20:31')
            ->assertJsonPath('meta.stale_pending', 2)
            ->assertJsonPath('meta.max_pending_external_id', 912803);
    }

    public function test_reconciliation_marks_stuck_external_id_gap_as_collection_gap(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-22 20:00:00', RaceTimingService::TIMEZONE));

        $stuckRace = SpeedwayRace::query()->create([
            'external_id' => '911365',
            'status' => 'pending',
            'race_hour' => '23',
            'race_minute' => '37',
            'pilot_odds_raw' => '3.00|2.75|5.25|4.00',
            'first_seen_at' => Carbon::parse('2026-06-21 23:30:00', RaceTimingService::TIMEZONE)->utc(),
        ]);

        SpeedwayRace::query()->create([
            'external_id' => '912803',
            'status' => 'pending',
            'race_hour' => '0',
            'race_minute' => '31',
            'pilot_odds_raw' => '3.00|2.75|5.25|4.00',
            'first_seen_at' => Carbon::parse('2026-06-22 19:55:00', RaceTimingService::TIMEZONE)->utc(),
        ]);

        $this->artisan('speedway:reconcile-pending-races')->assertExitCode(0);

        $stuckRace->refresh();
        $this->assertNotNull($stuckRace->stale_at);
        $this->assertSame('collection_gap', $stuckRace->stale_reason);
    }

    public function test_reconciliation_finds_settled_result_in_payload(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-22 15:00:00', RaceTimingService::TIMEZONE));

        $race = SpeedwayRace::query()->create([
            'external_id' => 'reconcile-settle-1',
            'status' => 'pending',
            'race_hour' => '18',
            'race_minute' => '45',
            'pilot_odds_raw' => '3.20|8.00|2.45|5.00',
            'first_seen_at' => now()->subMinutes(20),
        ]);

        $payload = SpeedwayPayload::query()->create([
            'source' => 'bbtips',
            'captured_at' => now()->subMinutes(2),
            'payload' => [[
                'Id' => 'reconcile-settle-1',
                'Hora' => '18',
                'Minutos' => '45',
                'Odds_Pilotos' => '3.20|8.00|2.45|5.00',
                'Vencedor' => '3',
                'Cor_Vencedor' => 'Amarelo',
                'Odd' => '2.45',
                'Nome_Piloto' => 'Piloto 3',
                'Previsao' => '3-1',
                'Odd_Previsao' => '2.10',
                'Previsao_Tricast' => '3-1-4',
            ]],
            'processing_status' => 'processed',
            'processed_at' => now()->subMinute(),
        ]);

        $this->artisan('speedway:reconcile-pending-races')->assertExitCode(0);

        $race->refresh();
        $this->assertSame('settled', $race->status);
        $this->assertSame(3, $race->winner_position);
        $this->assertNull($race->stale_at);
        $this->assertSame($payload->id, $race->last_payload_id);
    }

    public function test_reconciliation_marks_collection_gap_when_no_result_found(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-22 15:00:00', RaceTimingService::TIMEZONE));

        $race = SpeedwayRace::query()->create([
            'external_id' => 'reconcile-gap-1',
            'status' => 'pending',
            'race_hour' => '18',
            'race_minute' => '40',
            'pilot_odds_raw' => '2.00|3.00|4.00|5.00',
            'first_seen_at' => now()->subMinutes(25),
        ]);

        $this->artisan('speedway:reconcile-pending-races')->assertExitCode(0);

        $race->refresh();
        $this->assertSame('pending', $race->status);
        $this->assertNotNull($race->stale_at);
        $this->assertSame('collection_gap', $race->stale_reason);
    }

    /**
     * @return array{race_hour: string, race_minute: string}
     */
    private function virtualSlotFromBrazil(RaceTimingService $timingService, Carbon $brazilTime): array
    {
        return [
            'race_hour' => (string) $timingService->virtualHourFromBrazil((int) $brazilTime->format('G')),
            'race_minute' => $brazilTime->format('i'),
        ];
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
