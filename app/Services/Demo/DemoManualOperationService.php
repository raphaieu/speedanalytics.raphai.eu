<?php

namespace App\Services\Demo;

use App\Enums\Demo\BankrollTransactionType;
use App\Enums\Demo\DemoBetType;
use App\Enums\Demo\DemoMarketType;
use App\Enums\Demo\DemoOperationOrigin;
use App\Enums\Demo\DemoOperationResult;
use App\Enums\Demo\DemoOperationStatus;
use App\Enums\Demo\RuleCompliance;
use App\Exceptions\Demo\DemoOperationAlreadySettledException;
use App\Exceptions\Demo\InsufficientDemoBalanceException;
use App\Models\BankrollTransaction;
use App\Models\DemoAccount;
use App\Models\DemoOperation;
use App\Models\JournalEntry;
use App\Models\SpeedwayRace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DemoManualOperationService
{
    public function __construct(
        private readonly DemoAccountService $accountService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createManualOperation(array $data): DemoOperation
    {
        return DB::transaction(function () use ($data): DemoOperation {
            $account = isset($data['demo_account_id'])
                ? DemoAccount::query()->lockForUpdate()->findOrFail($data['demo_account_id'])
                : DemoAccount::query()->lockForUpdate()->findOrFail(
                    $this->accountService->defaultManualAccount()->id
                );

            $stakeAmount = round((float) $data['stake_amount'], 2);
            $entryOdd = isset($data['entry_odd']) ? round((float) $data['entry_odd'], 2) : null;
            $potentialGrossReturn = isset($data['potential_gross_return'])
                ? round((float) $data['potential_gross_return'], 2)
                : $this->calculatePotentialGrossReturn(
                    $stakeAmount,
                    $entryOdd,
                    DemoMarketType::from($data['market_type']),
                );
            $potentialNetProfit = isset($data['potential_net_profit'])
                ? round((float) $data['potential_net_profit'], 2)
                : round($potentialGrossReturn - $stakeAmount, 2);

            $balanceBefore = (float) $account->current_balance;
            if ($balanceBefore < $stakeAmount) {
                throw InsufficientDemoBalanceException::forStake($balanceBefore, $stakeAmount);
            }

            $balanceAfterStake = round($balanceBefore - $stakeAmount, 2);
            $account->update(['current_balance' => $balanceAfterStake]);

            $operation = DemoOperation::query()->create([
                'demo_account_id' => $account->id,
                'user_id' => $data['user_id'] ?? null,
                'speedway_race_id' => $data['speedway_race_id'] ?? null,
                'origin' => DemoOperationOrigin::Manual,
                'market_type' => DemoMarketType::from($data['market_type']),
                'bet_type' => DemoBetType::from($data['bet_type']),
                'status' => DemoOperationStatus::Open,
                'result' => DemoOperationResult::Pending,
                'risk_enforced' => (bool) ($data['risk_enforced'] ?? false),
                'after_stop' => (bool) ($data['after_stop'] ?? false),
                'rule_compliance' => RuleCompliance::from($data['rule_compliance'] ?? RuleCompliance::NotApplicable->value),
                'mistake_type' => $data['mistake_type'] ?? null,
                'tags' => $data['tags'] ?? null,
                'entry_payload_json' => $data['entry_payload_json'],
                'context_snapshot_json' => $data['context_snapshot_json'] ?? null,
                'stake_amount' => $stakeAmount,
                'potential_gross_return' => $potentialGrossReturn,
                'potential_net_profit' => $potentialNetProfit,
                'bankroll_before' => $balanceBefore,
                'bankroll_after' => $balanceAfterStake,
                'entry_position' => $data['entry_position'] ?? null,
                'entry_color' => $data['entry_color'] ?? null,
                'entry_odd' => $entryOdd,
                'reason_snapshot' => $data['reason_snapshot'] ?? null,
                'opened_at' => $data['opened_at'] ?? now(),
            ]);

            BankrollTransaction::query()->create([
                'demo_account_id' => $account->id,
                'demo_operation_id' => $operation->id,
                'type' => BankrollTransactionType::OperationStake,
                'amount' => -$stakeAmount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfterStake,
                'description' => 'Stake da operação manual #'.$operation->id,
                'created_at' => now(),
            ]);

            if (isset($data['journal']) && is_array($data['journal'])) {
                $this->createJournalEntry($operation, $data['journal']);
            }

            return $operation->fresh(['journalEntry', 'speedwayRace']);
        });
    }

    /**
     * @return Collection<int, DemoOperation>
     */
    public function listOperations(?DemoAccount $account = null, ?DemoOperationStatus $status = null): Collection
    {
        $account ??= $this->accountService->defaultManualAccount();

        $query = DemoOperation::query()
            ->where('demo_account_id', $account->id)
            ->where('origin', DemoOperationOrigin::Manual)
            ->with(['journalEntry', 'speedwayRace'])
            ->orderByDesc('opened_at');

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function settleOperationExplicitly(DemoOperation $operation, array $data): DemoOperation
    {
        if ($operation->status === DemoOperationStatus::Settled) {
            throw DemoOperationAlreadySettledException::forOperation($operation->id);
        }

        $result = DemoOperationResult::from($data['result']);
        $stakeAmount = (float) $operation->stake_amount;

        return DB::transaction(function () use ($operation, $data, $result, $stakeAmount): DemoOperation {
            $operation = DemoOperation::query()->lockForUpdate()->findOrFail($operation->id);
            $account = DemoAccount::query()->lockForUpdate()->findOrFail($operation->demo_account_id);

            [$actualGrossReturn, $profitLoss, $actualNetProfit] = $this->resolveExplicitSettlementAmounts(
                $result,
                $stakeAmount,
                $data,
            );

            $balanceBefore = (float) $account->current_balance;
            $balanceAfter = $balanceBefore;
            $creditAmount = 0.0;

            if ($result === DemoOperationResult::Win && $actualGrossReturn > 0) {
                $creditAmount = $actualGrossReturn;
                $balanceAfter = round($balanceBefore + $actualGrossReturn, 2);
            } elseif ($result === DemoOperationResult::Void) {
                $creditAmount = $stakeAmount;
                $balanceAfter = round($balanceBefore + $stakeAmount, 2);
            }

            if ($creditAmount > 0) {
                $account->update(['current_balance' => $balanceAfter]);
            }

            BankrollTransaction::query()->create([
                'demo_account_id' => $account->id,
                'demo_operation_id' => $operation->id,
                'type' => BankrollTransactionType::OperationSettlement,
                'amount' => $creditAmount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => match ($result) {
                    DemoOperationResult::Win => 'Liquidação green da operação #'.$operation->id,
                    DemoOperationResult::Loss => 'Liquidação red da operação #'.$operation->id,
                    DemoOperationResult::Void => 'Liquidação void da operação #'.$operation->id,
                    default => 'Liquidação da operação #'.$operation->id,
                },
                'metadata_json' => [
                    'result' => $result->value,
                    'profit_loss' => $profitLoss,
                    'settlement_mode' => 'manual',
                ],
                'created_at' => now(),
            ]);

            $operation->update([
                'status' => DemoOperationStatus::Settled,
                'result' => $result,
                'actual_gross_return' => $actualGrossReturn,
                'actual_net_profit' => $actualNetProfit,
                'profit_loss' => $profitLoss,
                'bankroll_after' => $balanceAfter,
                'settled_at' => now(),
                'context_snapshot_json' => array_merge(
                    $operation->context_snapshot_json ?? [],
                    ['settlement' => ['mode' => 'manual', 'result' => $result->value]],
                ),
            ]);

            return $operation->fresh(['journalEntry', 'speedwayRace']);
        });
    }

    public function settleManualOperation(DemoOperation $operation, ?SpeedwayRace $race = null): DemoOperation
    {
        if ($operation->status === DemoOperationStatus::Settled) {
            throw DemoOperationAlreadySettledException::forOperation($operation->id);
        }

        return DB::transaction(function () use ($operation, $race): DemoOperation {
            $operation = DemoOperation::query()->lockForUpdate()->findOrFail($operation->id);
            $account = DemoAccount::query()->lockForUpdate()->findOrFail($operation->demo_account_id);

            $race ??= $operation->speedway_race_id
                ? SpeedwayRace::query()->find($operation->speedway_race_id)
                : null;

            $won = $this->determineWin($operation, $race);
            $stakeAmount = (float) $operation->stake_amount;
            $entryOdd = (float) ($operation->entry_odd ?? 0);

            if ($won && $entryOdd > 0) {
                $actualGrossReturn = round($stakeAmount * $entryOdd, 2);
                $actualNetProfit = round($actualGrossReturn - $stakeAmount, 2);
                $profitLoss = $actualNetProfit;
                $result = DemoOperationResult::Win;
            } else {
                $actualGrossReturn = 0.0;
                $actualNetProfit = round(-$stakeAmount, 2);
                $profitLoss = $actualNetProfit;
                $result = DemoOperationResult::Loss;
            }

            $balanceBefore = (float) $account->current_balance;
            $balanceAfter = $balanceBefore;

            if ($won && $actualGrossReturn > 0) {
                $balanceAfter = round($balanceBefore + $actualGrossReturn, 2);
                $account->update(['current_balance' => $balanceAfter]);

                BankrollTransaction::query()->create([
                    'demo_account_id' => $account->id,
                    'demo_operation_id' => $operation->id,
                    'type' => BankrollTransactionType::OperationSettlement,
                    'amount' => $actualGrossReturn,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                    'description' => 'Liquidação green da operação #'.$operation->id,
                    'metadata_json' => [
                        'result' => $result->value,
                        'profit_loss' => $profitLoss,
                    ],
                    'created_at' => now(),
                ]);
            } else {
                BankrollTransaction::query()->create([
                    'demo_account_id' => $account->id,
                    'demo_operation_id' => $operation->id,
                    'type' => BankrollTransactionType::OperationSettlement,
                    'amount' => 0,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                    'description' => 'Liquidação red da operação #'.$operation->id,
                    'metadata_json' => [
                        'result' => $result->value,
                        'profit_loss' => $profitLoss,
                    ],
                    'created_at' => now(),
                ]);
            }

            $operation->update([
                'status' => DemoOperationStatus::Settled,
                'result' => $result,
                'actual_gross_return' => $actualGrossReturn,
                'actual_net_profit' => $actualNetProfit,
                'profit_loss' => $profitLoss,
                'bankroll_after' => $balanceAfter,
                'settled_at' => now(),
                'context_snapshot_json' => array_merge(
                    $operation->context_snapshot_json ?? [],
                    [
                        'settlement' => [
                            'won' => $won,
                            'race_id' => $race?->id,
                            'race_status' => $race?->status,
                        ],
                    ],
                ),
            ]);

            return $operation->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createJournalEntry(DemoOperation $operation, array $data): JournalEntry
    {
        return JournalEntry::query()->create([
            'user_id' => $data['user_id'] ?? $operation->user_id,
            'demo_operation_id' => $operation->id,
            'note' => $data['note'],
            'emotion' => $data['emotion'] ?? null,
            'confidence_level' => $data['confidence_level'] ?? null,
            'discipline_score' => $data['discipline_score'] ?? null,
            'tags_json' => $data['tags_json'] ?? null,
            'mistake_type' => $data['mistake_type'] ?? null,
            'ai_summary' => $data['ai_summary'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: float, 1: float, 2: float}
     */
    private function resolveExplicitSettlementAmounts(
        DemoOperationResult $result,
        float $stakeAmount,
        array $data,
    ): array {
        if ($result === DemoOperationResult::Void) {
            return [$stakeAmount, 0.0, 0.0];
        }

        if ($result === DemoOperationResult::Loss) {
            $profitLoss = isset($data['profit_loss'])
                ? round((float) $data['profit_loss'], 2)
                : round(-$stakeAmount, 2);

            return [0.0, $profitLoss, $profitLoss];
        }

        if (isset($data['actual_gross_return'])) {
            $actualGrossReturn = round((float) $data['actual_gross_return'], 2);
            $actualNetProfit = round($actualGrossReturn - $stakeAmount, 2);

            return [$actualGrossReturn, $actualNetProfit, $actualNetProfit];
        }

        if (isset($data['profit_loss'])) {
            $actualNetProfit = round((float) $data['profit_loss'], 2);
            $actualGrossReturn = round($stakeAmount + $actualNetProfit, 2);

            return [$actualGrossReturn, $actualNetProfit, $actualNetProfit];
        }

        throw new InvalidArgumentException('Informe actual_gross_return ou profit_loss para liquidação green.');
    }

    private function calculatePotentialGrossReturn(
        float $stake,
        ?float $entryOdd,
        DemoMarketType $marketType,
    ): float {
        if ($entryOdd === null || $entryOdd <= 0) {
            return $stake;
        }

        return round($stake * $entryOdd, 2);
    }

    private function determineWin(DemoOperation $operation, ?SpeedwayRace $race): bool
    {
        if ($race === null || $race->status !== 'settled') {
            return false;
        }

        return match ($operation->market_type) {
            DemoMarketType::Winner => $operation->entry_position !== null
                && $race->winner_position === $operation->entry_position,
            DemoMarketType::Forecast => $this->ordersMatch(
                $operation->entry_payload_json['order'] ?? null,
                $race->result_forecast_order,
            ),
            DemoMarketType::Tricast => $this->ordersMatch(
                $operation->entry_payload_json['order'] ?? null,
                $race->result_tricast_order,
            ),
        };
    }

    private function ordersMatch(mixed $entryOrder, ?string $resultOrder): bool
    {
        if (! is_string($entryOrder) || $resultOrder === null) {
            return false;
        }

        return trim($entryOrder) === trim($resultOrder);
    }
}
