<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SpeedwayRace;
use App\Services\Demo\DemoQuickEntryBuilder;
use App\Services\Speedway\RaceMetricsService;
use App\Services\Speedway\RaceTimingService;
use App\Support\SpeedwayRacePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DemoPendingRaceController extends Controller
{
    public function index(
        Request $request,
        RaceMetricsService $metricsService,
        DemoQuickEntryBuilder $quickEntryBuilder,
        RaceTimingService $timingService,
    ): JsonResponse {
        $limit = min(max((int) $request->integer('limit', 12), 1), 50);

        $allPending = SpeedwayRace::query()
            ->where('status', 'pending')
            ->orderByDesc('external_id')
            ->get();

        $maxPendingExternalId = $timingService->maxPendingExternalId($allPending);

        $actionable = $allPending
            ->filter(fn (SpeedwayRace $race) => $timingService->isActionablePending(
                $race,
                null,
                $maxPendingExternalId,
            ))
            ->sortBy(fn (SpeedwayRace $race) => $timingService->analyze(
                $race,
                null,
                $maxPendingExternalId,
            )['starts_at_iso'] ?? '9999')
            ->take($limit)
            ->values();

        $staleCount = $allPending
            ->filter(fn (SpeedwayRace $race) => $timingService->analyze(
                $race,
                null,
                $maxPendingExternalId,
            )['is_stale'])
            ->count();

        return response()->json([
            'data' => $actionable->map(function (SpeedwayRace $race) use (
                $metricsService,
                $quickEntryBuilder,
                $timingService,
                $maxPendingExternalId,
            ) {
                $calculated = $metricsService->calculate($race);

                return array_merge(
                    SpeedwayRacePresenter::pendingForDemo($race, $calculated, $maxPendingExternalId),
                    ['quick_entries' => $quickEntryBuilder->build($race, $calculated)],
                );
            })->values(),
            'meta' => [
                'total' => $actionable->count(),
                'limit' => $limit,
                'pending_total' => $allPending->count(),
                'stale_pending' => $staleCount,
                'actionable' => $actionable->count(),
                'max_pending_external_id' => $maxPendingExternalId,
            ],
        ]);
    }
}
