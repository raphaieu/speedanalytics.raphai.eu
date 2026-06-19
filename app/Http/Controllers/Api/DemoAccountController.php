<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Demo\DemoAccountService;
use App\Support\DemoPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DemoAccountController extends Controller
{
    public function __construct(
        private readonly DemoAccountService $accountService,
    ) {}

    public function show(): JsonResponse
    {
        $account = $this->accountService->defaultManualAccount();

        return response()->json([
            'data' => DemoPresenter::account($account),
        ]);
    }

    public function adjustBankroll(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'not_in:0'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $account = $this->accountService->defaultManualAccount();
        $transaction = $this->accountService->adjustBankroll(
            $account,
            (float) $validated['amount'],
            $validated['description'] ?? null,
        );

        return response()->json([
            'data' => [
                'account' => DemoPresenter::account($account->fresh()),
                'transaction' => [
                    'id' => $transaction->id,
                    'type' => $transaction->type->value,
                    'amount' => $transaction->amount,
                    'balance_before' => $transaction->balance_before,
                    'balance_after' => $transaction->balance_after,
                    'description' => $transaction->description,
                    'created_at' => $transaction->created_at?->toIso8601String(),
                ],
            ],
        ]);
    }

    public function bankrollCurve(): JsonResponse
    {
        $account = $this->accountService->defaultManualAccount();

        return response()->json([
            'data' => DemoPresenter::bankrollCurve(
                $this->accountService->bankrollCurve($account),
            ),
        ]);
    }
}
