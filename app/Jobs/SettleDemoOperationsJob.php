<?php

namespace App\Jobs;

use App\Enums\Demo\DemoOperationOrigin;
use App\Enums\Demo\DemoOperationStatus;
use App\Exceptions\Demo\DemoOperationAlreadySettledException;
use App\Models\DemoOperation;
use App\Services\Demo\DemoManualOperationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SettleDemoOperationsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public ?int $raceId = null) {}

    public function handle(DemoManualOperationService $operationService): void
    {
        $query = DemoOperation::query()
            ->where('status', DemoOperationStatus::Open)
            ->where('origin', DemoOperationOrigin::Manual)
            ->whereNotNull('speedway_race_id')
            ->whereHas('speedwayRace', fn ($builder) => $builder->where('status', 'settled'));

        if ($this->raceId !== null) {
            $query->where('speedway_race_id', $this->raceId);
        }

        $operationIds = $query->pluck('id');

        foreach ($operationIds as $operationId) {
            $operation = DemoOperation::query()->with('speedwayRace')->find($operationId);

            if ($operation === null || $operation->status !== DemoOperationStatus::Open) {
                continue;
            }

            try {
                $operationService->settleManualOperation($operation, $operation->speedwayRace);
            } catch (DemoOperationAlreadySettledException) {
                continue;
            }
        }
    }
}
