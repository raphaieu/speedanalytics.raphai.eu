<?php

namespace App\Http\Controllers\Api;

use App\Enums\Demo\DemoOperationStatus;
use App\Exceptions\Demo\DemoOperationAlreadySettledException;
use App\Exceptions\Demo\InsufficientDemoBalanceException;
use App\Http\Controllers\Controller;
use App\Models\DemoOperation;
use App\Services\Demo\DemoManualOperationService;
use App\Support\DemoPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class DemoOperationController extends Controller
{
    public function __construct(
        private readonly DemoManualOperationService $operationService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:open,settled'],
        ]);

        $status = isset($validated['status'])
            ? DemoOperationStatus::from($validated['status'])
            : null;

        $operations = $this->operationService->listOperations(status: $status);

        return response()->json([
            'data' => $operations->map(fn (DemoOperation $operation) => DemoPresenter::operation($operation))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'speedway_race_id' => ['nullable', 'integer', 'exists:speedway_races,id'],
            'market_type' => ['required', 'in:winner,forecast,tricast'],
            'bet_type' => ['required', 'in:single,combo'],
            'entry_payload_json' => ['required', 'array'],
            'stake_amount' => ['required', 'numeric', 'min:0.01'],
            'entry_odd' => ['nullable', 'numeric', 'min:0.01'],
            'potential_gross_return' => ['nullable', 'numeric', 'min:0'],
            'potential_net_profit' => ['nullable', 'numeric'],
            'risk_enforced' => ['sometimes', 'boolean'],
            'after_stop' => ['sometimes', 'boolean'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:64'],
            'entry_position' => ['nullable', 'integer', 'min:1', 'max:4'],
            'entry_color' => ['nullable', 'string', 'max:32'],
            'rule_compliance' => ['nullable', 'in:compliant,violated,not_applicable'],
            'mistake_type' => ['nullable', 'string', 'max:64'],
            'note' => ['nullable', 'string', 'max:2000'],
            'journal_tags' => ['nullable', 'array'],
            'journal_tags.*' => ['string', 'max:64'],
            'emotion' => ['nullable', 'string', 'max:64'],
            'context_snapshot_json' => ['nullable', 'array'],
        ]);

        if (isset($validated['note'])) {
            $validated['journal'] = [
                'note' => $validated['note'],
                'emotion' => $validated['emotion'] ?? null,
                'tags_json' => $validated['journal_tags'] ?? null,
            ];
        }

        if ($validated['market_type'] === 'winner' && ! isset($validated['entry_odd'])) {
            return response()->json(['message' => 'Winner exige odd de entrada.'], 422);
        }

        try {
            $operation = $this->operationService->createManualOperation($validated);
        } catch (InsufficientDemoBalanceException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'data' => DemoPresenter::operation($operation),
        ], 201);
    }

    public function settle(Request $request, DemoOperation $operation): JsonResponse
    {
        $validated = $request->validate([
            'result' => ['required', 'in:win,loss,void'],
            'actual_gross_return' => ['nullable', 'numeric', 'min:0'],
            'profit_loss' => ['nullable', 'numeric'],
        ]);

        if ($validated['result'] === 'win'
            && ! isset($validated['actual_gross_return'])
            && ! isset($validated['profit_loss'])) {
            return response()->json([
                'message' => 'Informe actual_gross_return ou profit_loss para liquidação green.',
            ], 422);
        }

        try {
            $settled = $this->operationService->settleOperationExplicitly($operation, $validated);
        } catch (DemoOperationAlreadySettledException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'data' => DemoPresenter::operation($settled),
        ]);
    }

    public function storeJournal(Request $request, DemoOperation $operation): JsonResponse
    {
        if ($operation->journalEntry !== null) {
            return response()->json(['message' => 'Esta operação já possui entrada no diário.'], 422);
        }

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
            'emotion' => ['nullable', 'string', 'max:64'],
            'confidence_level' => ['nullable', 'integer', 'min:1', 'max:10'],
            'discipline_score' => ['nullable', 'integer', 'min:1', 'max:10'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:64'],
            'mistake_type' => ['nullable', 'string', 'max:64'],
        ]);

        $entry = $this->operationService->createJournalEntry($operation, [
            'note' => $validated['note'],
            'emotion' => $validated['emotion'] ?? null,
            'confidence_level' => $validated['confidence_level'] ?? null,
            'discipline_score' => $validated['discipline_score'] ?? null,
            'tags_json' => $validated['tags'] ?? null,
            'mistake_type' => $validated['mistake_type'] ?? null,
        ]);

        return response()->json([
            'data' => DemoPresenter::journal($entry),
        ], 201);
    }
}
