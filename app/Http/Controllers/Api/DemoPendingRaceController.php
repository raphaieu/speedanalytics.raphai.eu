<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SpeedwayRace;
use App\Services\Demo\DemoQuickEntryBuilder;
use App\Services\Speedway\RaceMetricsService;
use App\Support\SpeedwayRacePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DemoPendingRaceController extends Controller
{
    public function index(
        Request $request,
        RaceMetricsService $metricsService,
        DemoQuickEntryBuilder $quickEntryBuilder,
    ): JsonResponse {
        $limit = min(max((int) $request->integer('limit', 12), 1), 50);

        $races = SpeedwayRace::query()
            ->where('status', 'pending')
            ->orderBy('external_id')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $races->map(function (SpeedwayRace $race) use ($metricsService, $quickEntryBuilder) {
                $calculated = $metricsService->calculate($race);

                return array_merge(
                    SpeedwayRacePresenter::pendingForDemo($race, $calculated),
                    ['quick_entries' => $quickEntryBuilder->build($race, $calculated)],
                );
            })->values(),
            'meta' => [
                'total' => $races->count(),
                'limit' => $limit,
            ],
        ]);
    }
}
