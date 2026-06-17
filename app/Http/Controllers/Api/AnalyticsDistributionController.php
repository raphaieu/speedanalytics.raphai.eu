<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SpeedwayRace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AnalyticsDistributionController extends Controller
{
    private const TIMEZONE = 'America/Sao_Paulo';

    /**
     * @var array<int, array{key: string, label: string, min: float|null, max: float|null}>
     */
    private const FAVORITE_ODD_BANDS = [
        ['key' => 'lt_2', 'label' => '< 2.00', 'min' => null, 'max' => 2.0],
        ['key' => 'from_2_to_3', 'label' => '2.00 - 2.99', 'min' => 2.0, 'max' => 3.0],
        ['key' => 'from_3_to_5', 'label' => '3.00 - 4.99', 'min' => 3.0, 'max' => 5.0],
        ['key' => 'gte_5', 'label' => '>= 5.00', 'min' => 5.0, 'max' => null],
    ];

    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'hour_from' => ['nullable', 'integer', 'min:0', 'max:23'],
            'hour_to' => ['nullable', 'integer', 'min:0', 'max:23'],
            'only_validated' => ['nullable', 'in:1,0,true,false'],
        ]);

        $onlyValidated = $request->boolean('only_validated');
        $statusColumn = 'status';
        $favoriteOddColumn = 'favorite_odd';
        $winnerFavoriteColumn = 'winner_was_favorite';
        $filters = [
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
            'hour_from' => $validated['hour_from'] ?? null,
            'hour_to' => $validated['hour_to'] ?? null,
            'only_validated' => $onlyValidated,
            'status' => 'settled',
        ];

        $scope = SpeedwayRace::query()->where($statusColumn, 'settled');

        if ($onlyValidated) {
            $scope->where(function (Builder $query): void {
                $query->whereNotNull('raw_pending_payload')
                    ->orWhereNotNull('first_seen_at');
            });
        }

        if (($validated['date_from'] ?? null) && ($validated['date_to'] ?? null)) {
            $from = Carbon::parse($validated['date_from'], self::TIMEZONE)->startOfDay()->utc();
            $to = Carbon::parse($validated['date_to'], self::TIMEZONE)->endOfDay()->utc();
            $scope->whereBetween('first_seen_at', [$from, $to]);
        }

        if (
            array_key_exists('hour_from', $validated)
            && array_key_exists('hour_to', $validated)
            && $validated['hour_from'] !== null
            && $validated['hour_to'] !== null
        ) {
            $scope->whereRaw('CAST(race_hour AS UNSIGNED) BETWEEN ? AND ?', [
                (int) $validated['hour_from'],
                (int) $validated['hour_to'],
            ]);
        }

        $bands = [];

        foreach (self::FAVORITE_ODD_BANDS as $band) {
            $bandScope = clone $scope;

            if ($band['min'] === null) {
                $bandScope->where($favoriteOddColumn, '<', $band['max']);
            } elseif ($band['max'] === null) {
                $bandScope->where($favoriteOddColumn, '>=', $band['min']);
            } else {
                $bandScope
                    ->where($favoriteOddColumn, '>=', $band['min'])
                    ->where($favoriteOddColumn, '<', $band['max']);
            }

            $total = (int) (clone $bandScope)->whereNotNull($winnerFavoriteColumn)->count();
            $wins = (int) (clone $bandScope)->where($winnerFavoriteColumn, true)->count();

            $bands[] = [
                'key' => $band['key'],
                'label' => $band['label'],
                'races' => $total,
                'wins' => $wins,
                'win_rate' => $total > 0 ? round(($wins / $total) * 100, 2) : 0.0,
            ];
        }

        return response()->json([
            'filters' => $filters,
            'favorite_odd_bands' => $bands,
        ]);
    }
}
