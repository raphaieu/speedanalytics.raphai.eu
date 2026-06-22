<?php

namespace Tests\Feature\Demo;

use App\Enums\Demo\BankrollTransactionType;
use App\Enums\Demo\DemoOperationResult;
use App\Enums\Demo\DemoOperationStatus;
use App\Jobs\SettleDemoOperationsJob;
use App\Models\DemoAccount;
use App\Models\SpeedwayRace;
use App\Services\Demo\DemoAccountService;
use App\Services\Demo\DemoManualOperationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSettlementTest extends TestCase
{
    use RefreshDatabase;

    private DemoAccountService $accountService;

    private DemoManualOperationService $operationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accountService = app(DemoAccountService::class);
        $this->operationService = app(DemoManualOperationService::class);
    }

    public function test_loss_settlement_does_not_double_debit_bankroll(): void
    {
        $account = $this->accountService->defaultManualAccount();
        $this->assertEquals('100.00', $account->current_balance);

        $operation = $this->operationService->createManualOperation([
            'market_type' => 'winner',
            'bet_type' => 'single',
            'stake_amount' => 1,
            'entry_odd' => 2.75,
            'entry_position' => 1,
            'entry_payload_json' => ['position' => 1, 'odd' => 2.75],
            'risk_enforced' => false,
        ]);

        $this->assertEquals('99.00', $account->fresh()->current_balance);

        $settled = $this->operationService->settleOperationExplicitly($operation, [
            'result' => 'loss',
        ]);

        $this->assertSame(DemoOperationResult::Loss, $settled->result);
        $this->assertEquals('0.00', $settled->actual_gross_return);
        $this->assertEquals('-1.00', $settled->actual_net_profit);
        $this->assertEquals('-1.00', $settled->profit_loss);
        $this->assertEquals('99.00', $account->fresh()->current_balance);

        $this->assertDatabaseHas('bankroll_transactions', [
            'demo_operation_id' => $operation->id,
            'type' => BankrollTransactionType::OperationSettlement->value,
            'amount' => '0.00',
            'balance_after' => '99.00',
        ]);
    }

    public function test_win_settlement_credits_gross_return(): void
    {
        $account = $this->accountService->defaultManualAccount();

        $operation = $this->operationService->createManualOperation([
            'market_type' => 'winner',
            'bet_type' => 'single',
            'stake_amount' => 1,
            'entry_odd' => 8.06,
            'entry_position' => 4,
            'entry_payload_json' => ['position' => 4, 'odd' => 8.06],
            'risk_enforced' => false,
        ]);

        $this->assertEquals('99.00', $account->fresh()->current_balance);

        $settled = $this->operationService->settleOperationExplicitly($operation, [
            'result' => 'win',
        ]);

        $this->assertSame(DemoOperationResult::Win, $settled->result);
        $this->assertEquals('8.06', $settled->actual_gross_return);
        $this->assertEquals('7.06', $settled->actual_net_profit);
        $this->assertEquals('7.06', $settled->profit_loss);
        $this->assertEquals('107.06', $account->fresh()->current_balance);
    }

    public function test_void_settlement_returns_stake(): void
    {
        $account = $this->accountService->defaultManualAccount();

        $operation = $this->operationService->createManualOperation([
            'market_type' => 'winner',
            'bet_type' => 'single',
            'stake_amount' => 1,
            'entry_odd' => 3.00,
            'entry_position' => 1,
            'entry_payload_json' => ['position' => 1, 'odd' => 3.00],
            'risk_enforced' => false,
        ]);

        $this->assertEquals('99.00', $account->fresh()->current_balance);

        $settled = $this->operationService->settleOperationExplicitly($operation, [
            'result' => 'void',
        ]);

        $this->assertSame(DemoOperationResult::Void, $settled->result);
        $this->assertEquals('1.00', $settled->actual_gross_return);
        $this->assertEquals('0.00', $settled->profit_loss);
        $this->assertEquals('100.00', $account->fresh()->current_balance);
    }

    public function test_winner_auto_settles_green_when_position_matches(): void
    {
        $race = $this->createSettledRace([
            'external_id' => 'auto-winner-green',
            'winner_position' => 2,
        ]);

        $operation = $this->operationService->createManualOperation([
            'speedway_race_id' => $race->id,
            'market_type' => 'winner',
            'bet_type' => 'single',
            'stake_amount' => 1,
            'entry_odd' => 4.00,
            'entry_position' => 2,
            'entry_payload_json' => ['position' => 2, 'odd' => 4.00],
            'risk_enforced' => false,
        ]);

        SettleDemoOperationsJob::dispatchSync($race->id);

        $settled = $operation->fresh();
        $this->assertSame(DemoOperationResult::Win, $settled->result);
        $this->assertEquals('4.00', $settled->actual_gross_return);
        $this->assertEquals('3.00', $settled->profit_loss);
        $this->assertEquals('103.00', DemoAccount::query()->find($operation->demo_account_id)?->current_balance);
    }

    public function test_winner_auto_settles_red_without_extra_debit(): void
    {
        $race = $this->createSettledRace([
            'external_id' => 'auto-winner-red',
            'winner_position' => 1,
        ]);

        $operation = $this->operationService->createManualOperation([
            'speedway_race_id' => $race->id,
            'market_type' => 'winner',
            'bet_type' => 'single',
            'stake_amount' => 1,
            'entry_odd' => 4.00,
            'entry_position' => 2,
            'entry_payload_json' => ['position' => 2, 'odd' => 4.00],
            'risk_enforced' => false,
        ]);

        $this->assertEquals('99.00', DemoAccount::query()->find($operation->demo_account_id)?->current_balance);

        SettleDemoOperationsJob::dispatchSync($race->id);

        $settled = $operation->fresh();
        $this->assertSame(DemoOperationResult::Loss, $settled->result);
        $this->assertEquals('0.00', $settled->actual_gross_return);
        $this->assertEquals('-1.00', $settled->profit_loss);
        $this->assertEquals('99.00', DemoAccount::query()->find($operation->demo_account_id)?->current_balance);

        $this->assertDatabaseHas('bankroll_transactions', [
            'demo_operation_id' => $operation->id,
            'type' => BankrollTransactionType::OperationSettlement->value,
            'amount' => '0.00',
        ]);
    }

    public function test_forecast_auto_settles_loss_when_order_differs(): void
    {
        $race = $this->createSettledRace([
            'external_id' => 'auto-forecast-loss',
            'result_forecast_order' => '2-1',
            'result_tricast_order' => '2-1-3',
        ]);

        $operation = $this->operationService->createManualOperation([
            'speedway_race_id' => $race->id,
            'market_type' => 'forecast',
            'bet_type' => 'single',
            'stake_amount' => 1,
            'entry_odd' => 12.50,
            'entry_payload_json' => ['order' => '1-3', 'odd' => 12.50],
            'risk_enforced' => false,
        ]);

        SettleDemoOperationsJob::dispatchSync($race->id);

        $settled = $operation->fresh();
        $this->assertSame(DemoOperationStatus::Settled, $settled->status);
        $this->assertSame(DemoOperationResult::Loss, $settled->result);
        $this->assertEquals('-1.00', $settled->profit_loss);
        $this->assertSame('auto', $settled->context_snapshot_json['settlement']['mode'] ?? null);
        $this->assertEquals('99.00', DemoAccount::query()->find($operation->demo_account_id)?->current_balance);
    }

    public function test_forecast_auto_settles_win_when_order_matches(): void
    {
        $race = $this->createSettledRace([
            'external_id' => 'auto-forecast-win',
            'result_forecast_order' => '1-3',
            'result_tricast_order' => '1-3-2',
        ]);

        $operation = $this->operationService->createManualOperation([
            'speedway_race_id' => $race->id,
            'market_type' => 'forecast',
            'bet_type' => 'single',
            'stake_amount' => 2,
            'entry_odd' => 5.00,
            'entry_payload_json' => ['order' => '1-3', 'odd' => 5.00],
            'risk_enforced' => false,
        ]);

        SettleDemoOperationsJob::dispatchSync($race->id);

        $settled = $operation->fresh();
        $this->assertSame(DemoOperationResult::Win, $settled->result);
        $this->assertEquals('10.00', $settled->actual_gross_return);
        $this->assertEquals('8.00', $settled->profit_loss);
        $this->assertEquals('108.00', DemoAccount::query()->find($operation->demo_account_id)?->current_balance);
    }

    public function test_tricast_auto_settles_using_result_tricast_order(): void
    {
        $race = $this->createSettledRace([
            'external_id' => 'auto-tricast-win',
            'result_forecast_order' => '4-1',
            'result_tricast_order' => '4-1-2',
        ]);

        $operation = $this->operationService->createManualOperation([
            'speedway_race_id' => $race->id,
            'market_type' => 'tricast',
            'bet_type' => 'single',
            'stake_amount' => 1,
            'entry_odd' => 20.00,
            'entry_payload_json' => ['order' => '4-1-2', 'odd' => 20.00],
            'risk_enforced' => false,
        ]);

        SettleDemoOperationsJob::dispatchSync($race->id);

        $settled = $operation->fresh();
        $this->assertSame(DemoOperationResult::Win, $settled->result);
        $this->assertEquals('20.00', $settled->actual_gross_return);
        $this->assertEquals('19.00', $settled->profit_loss);
        $this->assertEquals('119.00', DemoAccount::query()->find($operation->demo_account_id)?->current_balance);
    }

    public function test_auto_settlement_is_idempotent(): void
    {
        $race = $this->createSettledRace([
            'external_id' => 'auto-idempotent',
            'winner_position' => 2,
            'result_forecast_order' => '2-1',
            'result_tricast_order' => '2-1-3',
        ]);

        $operation = $this->operationService->createManualOperation([
            'speedway_race_id' => $race->id,
            'market_type' => 'winner',
            'bet_type' => 'single',
            'stake_amount' => 1,
            'entry_odd' => 4.00,
            'entry_position' => 2,
            'entry_payload_json' => ['position' => 2, 'odd' => 4.00],
            'risk_enforced' => false,
        ]);

        SettleDemoOperationsJob::dispatchSync($race->id);
        $balanceAfterFirstRun = DemoAccount::query()->find($operation->demo_account_id)?->current_balance;

        SettleDemoOperationsJob::dispatchSync($race->id);
        $balanceAfterSecondRun = DemoAccount::query()->find($operation->demo_account_id)?->current_balance;

        $this->assertEquals('103.00', $balanceAfterFirstRun);
        $this->assertEquals($balanceAfterFirstRun, $balanceAfterSecondRun);

        $this->assertDatabaseCount('bankroll_transactions', 2);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createSettledRace(array $overrides = []): SpeedwayRace
    {
        return SpeedwayRace::query()->create(array_merge([
            'external_id' => 'demo-settled-race',
            'status' => 'settled',
            'pilot_odds_raw' => '3.20|2.75|5.00|8.00',
            'winner_position' => 1,
            'winner_odd' => 3.20,
            'result_forecast_order' => '1-2',
            'result_tricast_order' => '1-2-3',
            'first_seen_at' => now()->subMinutes(10),
            'settled_at' => now(),
        ], $overrides));
    }
}
