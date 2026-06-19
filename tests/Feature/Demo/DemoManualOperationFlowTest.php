<?php

namespace Tests\Feature\Demo;

use App\Enums\Demo\BankrollTransactionType;
use App\Enums\Demo\DemoOperationOrigin;
use App\Enums\Demo\DemoOperationResult;
use App\Enums\Demo\DemoOperationStatus;
use App\Enums\Demo\RuleCompliance;
use App\Models\DemoAccount;
use App\Models\DemoOperation;
use App\Models\SpeedwayRace;
use App\Services\Demo\DemoAccountService;
use App\Services\Demo\DemoManualOperationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoManualOperationFlowTest extends TestCase
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

    public function test_migration_seeds_default_manual_account(): void
    {
        $account = $this->accountService->defaultManualAccount();

        $this->assertSame('manual-default', $account->slug);
        $this->assertTrue($account->is_default);
        $this->assertEquals('100.00', $account->current_balance);
    }

    public function test_creates_manual_operation_without_race_and_records_risk_bypass(): void
    {
        $operation = $this->operationService->createManualOperation([
            'market_type' => 'winner',
            'bet_type' => 'single',
            'stake_amount' => 1,
            'entry_odd' => 2.75,
            'entry_position' => 2,
            'entry_color' => 'Vermelho',
            'risk_enforced' => false,
            'after_stop' => true,
            'rule_compliance' => 'violated',
            'mistake_type' => 'fomo',
            'tags' => ['entrada manual', 'FOMO'],
            'entry_payload_json' => [
                'position' => 2,
                'color' => 'Vermelho',
                'odd' => 2.75,
            ],
            'context_snapshot_json' => ['source' => 'manual_form'],
            'journal' => [
                'note' => 'Entrada manual fora do setup após stop.',
                'emotion' => 'ansioso',
                'tags_json' => ['FOMO'],
            ],
        ]);

        $account = $this->accountService->defaultManualAccount()->fresh();

        $this->assertSame(DemoOperationOrigin::Manual, $operation->origin);
        $this->assertNull($operation->speedway_race_id);
        $this->assertFalse($operation->risk_enforced);
        $this->assertTrue($operation->after_stop);
        $this->assertSame(RuleCompliance::Violated, $operation->rule_compliance);
        $this->assertSame('fomo', $operation->mistake_type);
        $this->assertSame(DemoOperationStatus::Open, $operation->status);
        $this->assertEquals('1.00', $operation->stake_amount);
        $this->assertEquals('2.75', $operation->potential_gross_return);
        $this->assertEquals('1.75', $operation->potential_net_profit);
        $this->assertEquals('99.00', $account->current_balance);
        $this->assertNotNull($operation->journalEntry);
        $this->assertSame('Entrada manual fora do setup após stop.', $operation->journalEntry->note);

        $this->assertDatabaseHas('bankroll_transactions', [
            'demo_operation_id' => $operation->id,
            'type' => BankrollTransactionType::OperationStake->value,
            'amount' => '-1.00',
            'balance_after' => '99.00',
        ]);
    }

    public function test_adjusts_bankroll_with_manual_transaction(): void
    {
        $account = $this->accountService->defaultManualAccount();

        $transaction = $this->accountService->adjustBankroll(
            $account,
            25,
            'Depósito manual de teste',
            ['reason' => 'seed_extra_units'],
        );

        $this->assertSame(BankrollTransactionType::ManualAdjustment, $transaction->type);
        $this->assertEquals('125.00', $transaction->balance_after);
        $this->assertEquals('125.00', $account->fresh()->current_balance);
    }

    public function test_settles_manual_winner_operation_as_green(): void
    {
        $race = SpeedwayRace::query()->create([
            'external_id' => 'demo-race-win',
            'status' => 'settled',
            'pilot_odds_raw' => '3.20|2.75|5.00|8.00',
            'winner_position' => 2,
            'winner_odd' => 2.75,
            'result_forecast_order' => '2-1',
            'result_tricast_order' => '2-1-3',
            'first_seen_at' => now()->subMinutes(5),
            'settled_at' => now(),
        ]);

        $operation = $this->operationService->createManualOperation([
            'speedway_race_id' => $race->id,
            'market_type' => 'winner',
            'bet_type' => 'single',
            'stake_amount' => 2,
            'entry_odd' => 2.75,
            'entry_position' => 2,
            'entry_payload_json' => [
                'position' => 2,
                'odd' => 2.75,
            ],
            'risk_enforced' => false,
            'rule_compliance' => 'not_applicable',
        ]);

        $settled = $this->operationService->settleManualOperation($operation, $race);
        $account = DemoAccount::query()->findOrFail($operation->demo_account_id);

        $this->assertSame(DemoOperationStatus::Settled, $settled->status);
        $this->assertSame(DemoOperationResult::Win, $settled->result);
        $this->assertEquals('5.50', $settled->actual_gross_return);
        $this->assertEquals('3.50', $settled->actual_net_profit);
        $this->assertEquals('3.50', $settled->profit_loss);
        $this->assertEquals('103.50', $account->fresh()->current_balance);

        $this->assertDatabaseHas('bankroll_transactions', [
            'demo_operation_id' => $operation->id,
            'type' => BankrollTransactionType::OperationSettlement->value,
            'amount' => '5.50',
        ]);
    }

    public function test_settles_manual_forecast_operation_as_red_when_order_differs(): void
    {
        $race = SpeedwayRace::query()->create([
            'external_id' => 'demo-race-forecast',
            'status' => 'settled',
            'pilot_odds_raw' => '3.20|8.00|2.45|5.00',
            'winner_position' => 3,
            'result_forecast_order' => '3-2',
            'result_tricast_order' => '3-2-4',
            'first_seen_at' => now()->subMinutes(5),
            'settled_at' => now(),
        ]);

        $operation = $this->operationService->createManualOperation([
            'speedway_race_id' => $race->id,
            'market_type' => 'forecast',
            'bet_type' => 'combo',
            'stake_amount' => 1,
            'entry_odd' => 7.50,
            'entry_payload_json' => [
                'order' => '3-1',
                'odd' => 7.50,
            ],
            'risk_enforced' => false,
        ]);

        $settled = $this->operationService->settleManualOperation($operation, $race);

        $this->assertSame(DemoOperationResult::Loss, $settled->result);
        $this->assertEquals('0.00', $settled->actual_gross_return);
        $this->assertEquals('-1.00', $settled->profit_loss);
        $this->assertEquals('99.00', DemoAccount::query()->find($operation->demo_account_id)?->current_balance);
    }

    public function test_settles_operation_explicitly_as_void_refunds_stake(): void
    {
        $operation = $this->operationService->createManualOperation([
            'market_type' => 'winner',
            'bet_type' => 'single',
            'stake_amount' => 3,
            'entry_odd' => 2.50,
            'entry_position' => 1,
            'entry_payload_json' => ['position' => 1],
            'risk_enforced' => false,
        ]);

        $settled = $this->operationService->settleOperationExplicitly($operation, [
            'result' => 'void',
        ]);

        $this->assertSame(DemoOperationResult::Void, $settled->result);
        $this->assertEquals('3.00', $settled->actual_gross_return);
        $this->assertEquals('0.00', $settled->profit_loss);
        $this->assertEquals('100.00', DemoAccount::query()->find($operation->demo_account_id)?->current_balance);
    }

    public function test_settles_operation_explicitly_as_win_with_profit_loss(): void
    {
        $operation = $this->operationService->createManualOperation([
            'market_type' => 'winner',
            'bet_type' => 'single',
            'stake_amount' => 2,
            'entry_odd' => 3.00,
            'entry_position' => 1,
            'entry_payload_json' => ['position' => 1],
            'risk_enforced' => false,
        ]);

        $settled = $this->operationService->settleOperationExplicitly($operation, [
            'result' => 'win',
            'profit_loss' => 4,
        ]);

        $this->assertSame(DemoOperationResult::Win, $settled->result);
        $this->assertEquals('6.00', $settled->actual_gross_return);
        $this->assertEquals('4.00', $settled->profit_loss);
        $this->assertEquals('104.00', DemoAccount::query()->find($operation->demo_account_id)?->current_balance);
    }
}
