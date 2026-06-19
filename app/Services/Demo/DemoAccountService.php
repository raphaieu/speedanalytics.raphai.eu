<?php

namespace App\Services\Demo;

use App\Enums\Demo\BankrollTransactionType;
use App\Models\BankrollTransaction;
use App\Models\DemoAccount;
use Illuminate\Support\Facades\DB;

class DemoAccountService
{
    public function defaultManualAccount(): DemoAccount
    {
        $account = DemoAccount::query()
            ->where('slug', 'manual-default')
            ->where('is_default', true)
            ->first();

        if ($account === null) {
            throw new \RuntimeException('Conta manual padrão não encontrada.');
        }

        return $account;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function adjustBankroll(
        DemoAccount $account,
        float $amount,
        ?string $description = null,
        array $metadata = [],
    ): BankrollTransaction {
        return DB::transaction(function () use ($account, $amount, $description, $metadata): BankrollTransaction {
            $locked = DemoAccount::query()->lockForUpdate()->findOrFail($account->id);
            $balanceBefore = (float) $locked->current_balance;
            $balanceAfter = round($balanceBefore + $amount, 2);

            $locked->update(['current_balance' => $balanceAfter]);

            return BankrollTransaction::query()->create([
                'demo_account_id' => $locked->id,
                'type' => BankrollTransactionType::ManualAdjustment,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => $description,
                'metadata_json' => $metadata !== [] ? $metadata : null,
                'created_at' => now(),
            ]);
        });
    }

    /**
     * @return array{
     *     initial_balance: string,
     *     current_balance: string,
     *     points: list<array{
     *         at: string,
     *         balance: string,
     *         type: string,
     *         label: string|null,
     *         operation_id: int|null
     *     }>
     * }
     */
    public function bankrollCurve(DemoAccount $account): array
    {
        $transactions = BankrollTransaction::query()
            ->where('demo_account_id', $account->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $points = [[
            'at' => $account->created_at?->toIso8601String() ?? now()->toIso8601String(),
            'balance' => (string) $account->initial_balance,
            'type' => 'initial',
            'label' => 'Saldo inicial',
            'operation_id' => null,
        ]];

        foreach ($transactions as $transaction) {
            $points[] = [
                'at' => $transaction->created_at?->toIso8601String() ?? now()->toIso8601String(),
                'balance' => (string) $transaction->balance_after,
                'type' => $transaction->type->value,
                'label' => $transaction->description,
                'operation_id' => $transaction->demo_operation_id,
            ];
        }

        return [
            'initial_balance' => (string) $account->initial_balance,
            'current_balance' => (string) $account->current_balance,
            'points' => $points,
        ];
    }
}
