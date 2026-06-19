<?php

namespace Tests\Feature;

use App\Jobs\ProcessSpeedwayPayloadJob;
use App\Models\SpeedwayPayload;
use App\Models\SpeedwayRace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpeedwayRaceMetricsFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_process_job_calculates_metrics_and_preserves_pre_race_odds_when_settled(): void
    {
        $pendingPayload = SpeedwayPayload::query()->create([
            'source' => 'bbtips',
            'captured_at' => now()->subMinutes(2),
            'payload' => [[
                'Id' => 'race-100',
                'Hora' => '20',
                'Minutos' => '22',
                'Odds_Pilotos' => '3.20|8.00|2.45|5.00',
            ]],
            'processing_status' => 'pending',
        ]);

        ProcessSpeedwayPayloadJob::dispatchSync($pendingPayload->id);

        $settledPayload = SpeedwayPayload::query()->create([
            'source' => 'bbtips',
            'captured_at' => now(),
            'payload' => [[
                'Id' => 'race-100',
                'Hora' => '20',
                'Minutos' => '22',
                'Odds_Pilotos' => '1.10|1.20|1.30|1.40',
                'Vencedor' => '3',
                'Cor_Vencedor' => 'Amarelo',
                'Odd' => '2.45',
                'Nome_Piloto' => 'Piloto 3',
                'Previsao' => '3-1',
                'Odd_Previsao' => '2.10',
                'Previsao_Tricast' => '3-1-4',
            ]],
            'processing_status' => 'pending',
        ]);

        ProcessSpeedwayPayloadJob::dispatchSync($settledPayload->id);

        $column = 'external_id';
        $race = SpeedwayRace::query()->firstWhere($column, 'race-100');
        $this->assertInstanceOf(SpeedwayRace::class, $race);

        $this->assertSame('settled', $race->status);
        $this->assertSame('3.20|8.00|2.45|5.00', $race->pilot_odds_raw);
        $this->assertSame(3, $race->favorite_position);
        $this->assertEquals('2.45', $race->favorite_odd);
        $this->assertSame(2, $race->underdog_position);
        $this->assertTrue($race->winner_was_favorite);
        $this->assertFalse($race->winner_was_underdog);
        $this->assertSame(1, $race->winner_odd_rank);
        $this->assertSame('3-1', $race->market_rank_forecast_order);
        $this->assertSame('3-1-4', $race->market_rank_tricast_order);
        $this->assertSame('3-1', $race->result_forecast_order);
        $this->assertEquals('2.10', $race->result_forecast_odd);
        $this->assertSame('3-1-4', $race->result_tricast_order);
        $this->assertSame(3, $race->rank_1_position);
        $this->assertTrue($race->forecast_hit);
        $this->assertTrue($race->tricast_winner_hit);
        $this->assertTrue($race->tricast_exact_hit);
    }

    public function test_artisan_command_recalculates_metrics_in_chunks(): void
    {
        SpeedwayRace::query()->create([
            'external_id' => 'race-200',
            'status' => 'settled',
            'pilot_odds_raw' => '3.20|8.00|2.45|5.00',
            'winner_position' => 3,
            'raw_result_payload' => [
                'Previsao' => '3-1',
                'Previsao_Tricast' => '3-1-4',
            ],
            'first_seen_at' => now()->subMinutes(1),
            'settled_at' => now(),
        ]);

        $this->artisan('speedway:recalculate-metrics', ['--chunk' => 1])
            ->assertExitCode(0);

        $column = 'external_id';
        $race = SpeedwayRace::query()->firstWhere($column, 'race-200');
        $this->assertInstanceOf(SpeedwayRace::class, $race);

        $this->assertSame(3, $race->favorite_position);
        $this->assertEquals('2.45', $race->favorite_odd);
        $this->assertSame(2, $race->underdog_position);
        $this->assertEquals('5.55', $race->odds_spread);
        $this->assertTrue($race->winner_was_favorite);
        $this->assertSame(1, $race->winner_odd_rank);
        $this->assertTrue($race->forecast_hit);
        $this->assertTrue($race->tricast_exact_hit);
    }
}
