<?php

namespace Tests\Feature\Demo;

use App\Models\DemoOperation;
use App\Models\SpeedwayRace;
use App\Services\Speedway\RaceTimingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DemoManualApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_default_manual_account(): void
    {
        $response = $this->getJson('/api/demo/account');

        $response->assertOk()
            ->assertJsonPath('data.slug', 'manual-default')
            ->assertJsonPath('data.current_balance', '100.00');
    }

    public function test_adjusts_bankroll_via_api(): void
    {
        $response = $this->postJson('/api/demo/account/adjust-bankroll', [
            'amount' => 10,
            'description' => 'Ajuste de teste',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.account.current_balance', '110.00')
            ->assertJsonPath('data.transaction.amount', '10.00');
    }

    public function test_returns_bankroll_curve_with_initial_and_transactions(): void
    {
        $operationId = $this->postJson('/api/demo/operations', [
            'market_type' => 'winner',
            'bet_type' => 'single',
            'stake_amount' => 2,
            'entry_odd' => 3.00,
            'entry_payload_json' => ['position' => 1, 'odd' => 3.00],
            'risk_enforced' => false,
        ])->json('data.id');

        $this->postJson("/api/demo/operations/{$operationId}/settle", [
            'result' => 'win',
        ])->assertOk();

        $response = $this->getJson('/api/demo/account/bankroll-curve');

        $response->assertOk()
            ->assertJsonPath('data.initial_balance', '100.00')
            ->assertJsonPath('data.current_balance', '104.00')
            ->assertJsonPath('data.points.0.type', 'initial')
            ->assertJsonPath('data.points.0.balance', '100.00')
            ->assertJsonCount(3, 'data.points');
    }

    public function test_settled_operation_exposes_settlement_mode(): void
    {
        $race = SpeedwayRace::query()->create([
            'external_id' => 'api-settlement-mode',
            'status' => 'settled',
            'pilot_odds_raw' => '3.20|2.75|5.00|8.00',
            'winner_position' => 1,
            'result_forecast_order' => '1-2',
            'result_tricast_order' => '1-2-3',
            'first_seen_at' => now()->subMinutes(5),
            'settled_at' => now(),
        ]);

        $operationId = $this->postJson('/api/demo/operations', [
            'speedway_race_id' => $race->id,
            'market_type' => 'winner',
            'bet_type' => 'single',
            'stake_amount' => 1,
            'entry_odd' => 3.20,
            'entry_position' => 1,
            'entry_payload_json' => ['position' => 1, 'odd' => 3.20],
            'risk_enforced' => false,
        ])->json('data.id');

        $this->postJson("/api/demo/operations/{$operationId}/settle", [
            'result' => 'win',
        ])->assertOk()
            ->assertJsonPath('data.settlement_mode', 'manual');

        $this->getJson('/api/demo/operations?status=settled')
            ->assertJsonPath('data.0.settlement_mode', 'manual');
    }

    public function test_operation_exposes_linked_race_result_fields(): void
    {
        $race = SpeedwayRace::query()->create([
            'external_id' => 'api-race-result-fields',
            'status' => 'settled',
            'race_hour' => '9',
            'race_minute' => '7',
            'pilot_odds_raw' => '3.20|8.00|2.45|5.00',
            'winner_position' => 4,
            'winner_color' => 'Roxo',
            'result_forecast_order' => '4-2',
            'result_tricast_order' => '4-2-1',
            'underdog_position' => 2,
            'underdog_odd' => 8.00,
            'first_seen_at' => now()->subMinutes(5),
            'settled_at' => now(),
        ]);

        $this->postJson('/api/demo/operations', [
            'speedway_race_id' => $race->id,
            'market_type' => 'forecast',
            'bet_type' => 'single',
            'stake_amount' => 1,
            'entry_odd' => 8.58,
            'entry_payload_json' => ['order' => '2-1', 'odd' => 8.58],
            'risk_enforced' => false,
        ])->assertCreated();

        $this->getJson('/api/demo/operations?status=open')
            ->assertJsonPath('data.0.race.external_id', 'api-race-result-fields')
            ->assertJsonPath('data.0.race.result_forecast_order', '4-2')
            ->assertJsonPath('data.0.race.result_tricast_order', '4-2-1')
            ->assertJsonPath('data.0.race.winner_position', 4)
            ->assertJsonPath('data.0.race.underdog_position', 2);
    }

    public function test_creates_manual_operation_via_api(): void
    {
        $response = $this->postJson('/api/demo/operations', [
            'market_type' => 'winner',
            'bet_type' => 'single',
            'stake_amount' => 1,
            'entry_odd' => 2.75,
            'entry_position' => 2,
            'entry_color' => 'Vermelho',
            'risk_enforced' => false,
            'after_stop' => true,
            'tags' => ['entrada manual'],
            'entry_payload_json' => [
                'position' => 2,
                'color' => 'Vermelho',
                'odd' => 2.75,
            ],
            'note' => 'Entrada após stop.',
            'journal_tags' => ['FOMO'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.after_stop', true)
            ->assertJsonPath('data.journal.note', 'Entrada após stop.');

        $this->assertDatabaseHas('demo_operations', [
            'id' => $response->json('data.id'),
            'after_stop' => true,
        ]);
    }

    public function test_lists_open_and_settled_operations(): void
    {
        $open = $this->postJson('/api/demo/operations', [
            'market_type' => 'winner',
            'bet_type' => 'single',
            'stake_amount' => 1,
            'entry_odd' => 2.50,
            'entry_payload_json' => ['position' => 1, 'odd' => 2.50],
            'risk_enforced' => false,
        ])->json('data.id');

        $settled = $this->postJson('/api/demo/operations', [
            'market_type' => 'winner',
            'bet_type' => 'single',
            'stake_amount' => 1,
            'entry_odd' => 2.50,
            'entry_payload_json' => ['position' => 1, 'odd' => 2.50],
            'risk_enforced' => false,
        ])->json('data.id');

        $this->postJson("/api/demo/operations/{$settled}/settle", [
            'result' => 'loss',
        ])->assertOk();

        $this->getJson('/api/demo/operations?status=open')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $open);

        $this->getJson('/api/demo/operations?status=settled')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $settled);
    }

    public function test_settles_operation_via_api_as_void(): void
    {
        $operationId = $this->postJson('/api/demo/operations', [
            'market_type' => 'winner',
            'bet_type' => 'single',
            'stake_amount' => 2,
            'entry_odd' => 3.00,
            'entry_payload_json' => ['position' => 1, 'odd' => 3.00],
            'risk_enforced' => false,
        ])->json('data.id');

        $response = $this->postJson("/api/demo/operations/{$operationId}/settle", [
            'result' => 'void',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.result', 'void')
            ->assertJsonPath('data.profit_loss', '0.00');

        $this->getJson('/api/demo/account')
            ->assertJsonPath('data.current_balance', '100.00');
    }

    public function test_settles_operation_via_api_as_win_without_manual_amounts(): void
    {
        $operationId = $this->postJson('/api/demo/operations', [
            'market_type' => 'winner',
            'bet_type' => 'single',
            'stake_amount' => 1,
            'entry_odd' => 2.00,
            'entry_payload_json' => ['position' => 1, 'odd' => 2.00],
            'risk_enforced' => false,
        ])->json('data.id');

        $this->postJson("/api/demo/operations/{$operationId}/settle", [
            'result' => 'win',
        ])
            ->assertOk()
            ->assertJsonPath('data.result', 'win')
            ->assertJsonPath('data.actual_gross_return', '2.00')
            ->assertJsonPath('data.profit_loss', '1.00');

        $this->getJson('/api/demo/account')
            ->assertJsonPath('data.current_balance', '101.00');
    }

    public function test_rejects_manual_win_settlement_without_amounts(): void
    {
        $operationId = $this->postJson('/api/demo/operations', [
            'market_type' => 'winner',
            'bet_type' => 'single',
            'stake_amount' => 1,
            'entry_odd' => 2.00,
            'entry_payload_json' => ['position' => 1, 'odd' => 2.00],
            'risk_enforced' => false,
        ])->json('data.id');

        $this->postJson("/api/demo/operations/{$operationId}/settle", [
            'result' => 'win',
            'settlement_mode' => 'manual',
        ])->assertStatus(422);
    }

    public function test_lists_pending_races_for_demo_picker(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-22 20:00:00', RaceTimingService::TIMEZONE));

        SpeedwayRace::query()->create([
            'external_id' => 'demo-pending-1',
            'status' => 'pending',
            'race_hour' => '0',
            'race_minute' => '15',
            'pilot_odds_raw' => '3.20|8.00|2.45|5.00',
            'rank_1_position' => 3,
            'rank_1_odd' => 2.45,
            'rank_2_position' => 1,
            'rank_2_odd' => 3.20,
            'rank_3_position' => 4,
            'rank_3_odd' => 5.00,
            'rank_4_position' => 2,
            'rank_4_odd' => 8.00,
            'market_rank_forecast_order' => '3-1',
            'market_rank_tricast_order' => '3-1-4',
            'favorite_position' => 3,
            'favorite_odd' => 2.45,
            'underdog_position' => 2,
            'underdog_odd' => 8.00,
            'first_seen_at' => now(),
        ]);

        SpeedwayRace::query()->create([
            'external_id' => 'demo-settled-1',
            'status' => 'settled',
            'pilot_odds_raw' => '2.00|3.00|4.00|5.00',
            'winner_position' => 1,
            'first_seen_at' => now()->subHour(),
            'settled_at' => now(),
        ]);

        $this->getJson('/api/demo/pending-races')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.external_id', 'demo-pending-1')
            ->assertJsonPath('data.0.market_rank_forecast_order', '3-1')
            ->assertJsonPath('data.0.pilot_odds.0.position', 1)
            ->assertJsonPath('data.0.quick_entries.0.id', 'winner_favorite')
            ->assertJsonPath('data.0.quick_entries.0.pricing_status', 'observed')
            ->assertJsonPath('data.0.quick_entries.2.id', 'forecast_suggested')
            ->assertJsonPath('data.0.quick_entries.2.label', 'Forecast 3-1')
            ->assertJsonPath('data.0.quick_entries.2.pricing_status', 'estimated')
            ->assertJsonPath('data.0.quick_entries.2.entry_odd', 5.10)
            ->assertJsonPath('data.0.quick_entries.3.id', 'tricast_suggested')
            ->assertJsonPath('data.0.quick_entries.3.label', 'Tricast 3-1-4');

        Carbon::setTestNow();
    }

    public function test_quick_entry_tricast_returns_estimated_pricing_status(): void
    {
        SpeedwayRace::query()->create([
            'external_id' => 'demo-pending-tricast',
            'status' => 'pending',
            'pilot_odds_raw' => '3.20|8.00|2.45|5.00',
            'rank_1_position' => 3,
            'rank_1_odd' => 2.45,
            'rank_2_position' => 1,
            'rank_2_odd' => 3.20,
            'rank_3_position' => 4,
            'rank_3_odd' => 5.00,
            'rank_4_position' => 2,
            'rank_4_odd' => 8.00,
            'market_rank_forecast_order' => '3-1',
            'market_rank_tricast_order' => '3-1-4',
            'first_seen_at' => now(),
        ]);

        $this->getJson('/api/demo/pending-races')
            ->assertJsonPath('data.0.quick_entries.3.id', 'tricast_suggested')
            ->assertJsonPath('data.0.quick_entries.3.pricing_status', 'estimated')
            ->assertJsonPath('data.0.quick_entries.3.bet_type', 'single')
            ->assertJsonPath('data.0.quick_entries.3.entry_odd', 13.72)
            ->assertJsonPath('data.0.quick_entries.3.label', 'Tricast 3-1-4');
    }

    public function test_creates_forecast_operation_with_estimated_pricing_status(): void
    {
        $response = $this->postJson('/api/demo/operations', [
            'market_type' => 'forecast',
            'bet_type' => 'single',
            'stake_amount' => 1,
            'entry_odd' => 5.40,
            'entry_payload_json' => [
                'order' => '4-1',
                'pricing_status' => 'estimated',
                'estimated_entry_odd' => 5.40,
                'selected_quick_entry_label' => 'Forecast rank 1-2',
            ],
            'risk_enforced' => false,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.bet_type', 'single')
            ->assertJsonPath('data.entry_payload_json.pricing_status', 'estimated');

        $this->assertEquals('5.40', $response->json('data.potential_gross_return'));
    }

    public function test_creates_forecast_operation_with_manual_pricing_status(): void
    {
        $response = $this->postJson('/api/demo/operations', [
            'market_type' => 'forecast',
            'bet_type' => 'single',
            'stake_amount' => 1,
            'entry_odd' => 5.63,
            'entry_payload_json' => [
                'order' => '4-1',
                'pricing_status' => 'manual',
                'estimated_entry_odd' => 5.40,
                'selected_quick_entry_label' => 'Forecast rank 1-2',
            ],
            'risk_enforced' => false,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.entry_payload_json.pricing_status', 'manual')
            ->assertJsonPath('data.entry_odd', '5.63');
    }

    public function test_rejects_winner_without_entry_odd(): void
    {
        $this->postJson('/api/demo/operations', [
            'market_type' => 'winner',
            'bet_type' => 'single',
            'stake_amount' => 1,
            'entry_payload_json' => ['position' => 1],
            'risk_enforced' => false,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Winner exige odd de entrada.');
    }

    public function test_stores_context_snapshot_with_manual_operation(): void
    {
        $race = SpeedwayRace::query()->create([
            'external_id' => 'demo-pending-snapshot',
            'status' => 'pending',
            'pilot_odds_raw' => '3.20|8.00|2.45|5.00',
            'first_seen_at' => now(),
        ]);

        $response = $this->postJson('/api/demo/operations', [
            'speedway_race_id' => $race->id,
            'market_type' => 'winner',
            'bet_type' => 'single',
            'stake_amount' => 1,
            'entry_position' => 3,
            'entry_odd' => 2.45,
            'entry_payload_json' => ['position' => 3, 'odd' => 2.45],
            'risk_enforced' => false,
            'context_snapshot_json' => [
                'source' => 'demo_manual_pending_picker',
                'external_id' => 'demo-pending-snapshot',
            ],
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('demo_operations', [
            'id' => $response->json('data.id'),
            'speedway_race_id' => $race->id,
        ]);

        $operation = DemoOperation::query()->findOrFail($response->json('data.id'));
        $this->assertSame('demo_manual_pending_picker', $operation->context_snapshot_json['source'] ?? null);
    }
}
