<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SpeedwayRace;
use App\Services\Speedway\RaceTimingService;
use App\Support\SpeedwayRacePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class RaceController extends Controller
{
    private const TIMEZONE = 'America/Sao_Paulo';

    private const RACES_PER_DAY = 480;

    public function index(Request $request, RaceTimingService $timingService): JsonResponse
    {
        $timezone = self::TIMEZONE;
        $today = now($timezone)->toDateString();
        $selectedMonth = $request->string('month')->toString()
            ?: now($timezone)->format('Y-m');
        $selectedDate = $request->string('date')->toString() ?: $today;

        if (! str_starts_with($selectedDate, $selectedMonth)) {
            $selectedDate = $this->defaultDateForMonth($timezone, $selectedMonth, $today);
        }

        $dayStart = Carbon::parse($selectedDate, $timezone)->startOfDay()->utc();
        $dayEnd = Carbon::parse($selectedDate, $timezone)->endOfDay()->utc();
        $perPage = min((int) $request->integer('per_page', self::RACES_PER_DAY), self::RACES_PER_DAY);

        $baseQuery = SpeedwayRace::query()
            ->whereBetween('first_seen_at', [$dayStart, $dayEnd]);

        $pendingForDay = (clone $baseQuery)->where('status', 'pending')->get();
        $globalPending = SpeedwayRace::query()->where('status', 'pending')->get();
        $maxPendingExternalId = $timingService->maxPendingExternalId($globalPending);

        $actionablePending = $pendingForDay->filter(
            fn (SpeedwayRace $race) => $timingService->isActionablePending($race, null, $maxPendingExternalId),
        );
        $stalePending = $pendingForDay->filter(
            fn (SpeedwayRace $race) => $timingService->analyze($race, null, $maxPendingExternalId)['is_stale'],
        );

        $dayCounts = [
            'total' => (clone $baseQuery)->count(),
            'upcoming' => $actionablePending->count(),
            'stale_pending' => $stalePending->count(),
            'settled' => (clone $baseQuery)->where('status', 'settled')->count(),
        ];

        $globalActionable = $globalPending->filter(
            fn (SpeedwayRace $race) => $timingService->isActionablePending($race, null, $maxPendingExternalId),
        );
        $globalStale = $globalPending->filter(
            fn (SpeedwayRace $race) => $timingService->analyze($race, null, $maxPendingExternalId)['is_stale'],
        );

        $paginator = (clone $baseQuery)
            ->orderByDesc('external_id')
            ->paginate($perPage);

        return response()->json([
            'data' => $paginator->getCollection()->map(
                fn (SpeedwayRace $race) => SpeedwayRacePresenter::summary($race, $maxPendingExternalId),
            )->values(),
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'day' => [
                    'date' => $selectedDate,
                    'is_today' => $selectedDate === $today,
                    'counts' => $dayCounts,
                ],
                'calendar' => [
                    'month' => $selectedMonth,
                    'years' => $this->availableYears($timezone),
                    'months' => $this->availableMonths($timezone, $selectedMonth),
                    'days' => $this->daysInMonth($timezone, $selectedMonth, $today),
                ],
                'global' => [
                    'total' => SpeedwayRace::query()->count(),
                    'upcoming' => $globalActionable->count(),
                    'stale_pending' => $globalStale->count(),
                ],
            ],
        ]);
    }

    private function defaultDateForMonth(string $timezone, string $yearMonth, string $today): string
    {
        if (str_starts_with($today, $yearMonth)) {
            return $today;
        }

        $days = $this->daysInMonth($timezone, $yearMonth, $today);

        return $days[0]['date'] ?? "{$yearMonth}-01";
    }

    /**
     * @return list<string>
     */
    private function availableYears(string $timezone): array
    {
        $first = SpeedwayRace::query()->whereNotNull('first_seen_at')->min('first_seen_at');

        if (! $first) {
            return [now($timezone)->format('Y')];
        }

        $startYear = (int) Carbon::parse($first)->timezone($timezone)->format('Y');
        $endYear = (int) now($timezone)->format('Y');
        $years = [];

        for ($year = $endYear; $year >= $startYear; $year--) {
            $years[] = (string) $year;
        }

        return $years;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function availableMonths(string $timezone, string $selectedMonth): array
    {
        $year = (int) substr($selectedMonth, 0, 4);
        $first = SpeedwayRace::query()->whereNotNull('first_seen_at')->min('first_seen_at');
        $currentMonth = (int) now($timezone)->format('n');
        $currentYear = (int) now($timezone)->format('Y');

        $startMonth = 1;
        if ($first && (int) Carbon::parse($first)->timezone($timezone)->format('Y') === $year) {
            $startMonth = (int) Carbon::parse($first)->timezone($timezone)->format('n');
        }

        $endMonth = $year === $currentYear ? $currentMonth : 12;
        $months = [];

        for ($m = $endMonth; $m >= $startMonth; $m--) {
            $value = sprintf('%04d-%02d', $year, $m);
            $label = Carbon::parse("{$value}-01", $timezone)->locale('pt_BR')->translatedFormat('M');
            $months[] = ['value' => $value, 'label' => $label];
        }

        return $months;
    }

    /**
     * @return list<array{date: string, total: int}>
     */
    private function daysInMonth(string $timezone, string $yearMonth, string $today): array
    {
        $start = Carbon::parse("{$yearMonth}-01", $timezone)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $days = [];

        for ($cursor = $end->copy(); $cursor >= $start; $cursor->subDay()) {
            $date = $cursor->toDateString();
            $dayStart = $cursor->copy()->startOfDay()->utc();
            $dayEnd = $cursor->copy()->endOfDay()->utc();
            $total = SpeedwayRace::query()->whereBetween('first_seen_at', [$dayStart, $dayEnd])->count();

            if ($total > 0 || $date === $today) {
                $days[] = ['date' => $date, 'total' => $total];
            }
        }

        return $days;
    }

    public function show(SpeedwayRace $race): JsonResponse
    {
        return response()->json([
            'data' => SpeedwayRacePresenter::detail($race),
        ]);
    }
}
