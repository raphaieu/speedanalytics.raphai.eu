<?php

namespace App\Services\Demo;

use App\Enums\Demo\BankrollTransactionType;
use App\Models\BankrollTransaction;
use App\Models\DemoAccount;
use App\Models\DemoOperation;
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
}
