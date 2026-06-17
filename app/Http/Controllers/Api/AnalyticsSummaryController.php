<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SpeedwayRace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AnalyticsSummaryController extends Controller
{
    private const TIMEZONE = 'America/Sao_Paulo';

    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'hour_from' => ['nullable', 'integer', 'min:0', 'max:23'],
            'hour_to' => ['nullable', 'integer', 'min:0', 'max:23'],
            'only_validated' => ['nullable', 'in:1,0,true,false'],
        ]);

        $statusColumn = 'status';
        $winnerFavoriteColumn = 'winner_was_favorite';
        $winnerUnderdogColumn = 'winner_was_underdog';
        $forecastHitColumn = 'forecast_hit';
        $tricastExactHitColumn = 'tricast_exact_hit';

        $onlyValidated = $request->boolean('only_validated');
        $filters = [
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
            'hour_from' => $validated['hour_from'] ?? null,
            'hour_to' => $validated['hour_to'] ?? null,
            'only_validated' => $onlyValidated,
            'status' => 'settled',
        ];

        $scope = SpeedwayRace::query();

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
            $hourFrom = (int) $validated['hour_from'];
            $hourTo = (int) $validated['hour_to'];

            $scope->whereRaw('CAST(race_hour AS UNSIGNED) BETWEEN ? AND ?', [$hourFrom, $hourTo]);
        }

        $settledScope = (clone $scope)->where($statusColumn, 'settled');

        $totals = [
            'races' => (int) (clone $settledScope)->count(),
            'validated_races' => (int) (clone $settledScope)->where(function (Builder $query): void {
                $query->whereNotNull('raw_pending_payload')
                    ->orWhereNotNull('first_seen_at');
            })->count(),
            'settled_races' => (int) (clone $scope)->where($statusColumn, 'settled')->count(),
            'pending_races' => (int) (clone $scope)->where($statusColumn, 'pending')->count(),
        ];

        $favoriteWins = (int) (clone $settledScope)->where($winnerFavoriteColumn, true)->count();
        $favoriteLosses = (int) (clone $settledScope)->where($winnerFavoriteColumn, false)->count();
        $favoriteBets = $favoriteWins + $favoriteLosses;
        $favoriteWinOddsSum = (float) ((clone $settledScope)
            ->where($winnerFavoriteColumn, true)
            ->sum('favorite_odd'));
        $favoriteProfit = $favoriteWinOddsSum - $favoriteWins - $favoriteLosses;
        $favoriteRoi = $favoriteBets > 0 ? ($favoriteProfit / $favoriteBets) * 100 : 0.0;

        $underdogWins = (int) (clone $settledScope)->where($winnerUnderdogColumn, true)->count();
        $underdogDecided = (int) (clone $settledScope)->whereNotNull($winnerUnderdogColumn)->count();

        $forecastTotal = (int) (clone $settledScope)->whereNotNull($forecastHitColumn)->count();
        $forecastHits = (int) (clone $settledScope)->where($forecastHitColumn, true)->count();

        $tricastTotal = (int) (clone $settledScope)->whereNotNull($tricastExactHitColumn)->count();
        $tricastHits = (int) (clone $settledScope)->where($tricastExactHitColumn, true)->count();
        $settledTotal = $totals['settled_races'];

        $oddsAggregates = (clone $settledScope)->selectRaw('
            COALESCE(AVG(winner_odd), 0) as average_winner_odd,
            COALESCE(AVG(favorite_odd), 0) as average_favorite_odd,
            COALESCE(AVG(odds_spread), 0) as average_spread,
            COALESCE(AVG(house_margin), 0) as average_house_margin
        ')->first();

        return response()->json([
            'filters' => $filters,
            'totals' => $totals,
            'favorite' => [
                'wins' => $favoriteWins,
                'losses' => $favoriteLosses,
                'win_rate' => $this->rate($favoriteWins, $favoriteBets),
                'theoretical_roi' => round($favoriteRoi, 2),
            ],
            'underdog' => [
                'wins' => $underdogWins,
                'win_rate' => $this->rate($underdogWins, $underdogDecided),
            ],
            'forecast' => [
                'total' => $forecastTotal,
                'hits' => $forecastHits,
                'hit_rate' => $this->rate($forecastHits, $forecastTotal),
            ],
            'tricast' => [
                'total' => $tricastTotal,
                'hits' => $tricastHits,
                'hit_rate' => $this->rate($tricastHits, $tricastTotal),
            ],
            'odds' => [
                'average_winner_odd' => round((float) ($oddsAggregates?->average_winner_odd ?? 0), 2),
                'average_favorite_odd' => round((float) ($oddsAggregates?->average_favorite_odd ?? 0), 2),
                'average_spread' => round((float) ($oddsAggregates?->average_spread ?? 0), 2),
                'average_house_margin' => round((float) ($oddsAggregates?->average_house_margin ?? 0) * 100, 2),
            ],
            'metadata' => [
                'forecast_applicable_count' => $forecastTotal,
                'forecast_null_count' => max($settledTotal - $forecastTotal, 0),
                'tricast_applicable_count' => $tricastTotal,
                'tricast_null_count' => max($settledTotal - $tricastTotal, 0),
                'house_margin_format' => 'decimal_fraction_in_db_percentage_in_api',
                'percentage_format' => 'percentage_points_in_api',
            ],
        ]);
    }

    private function rate(int $hits, int $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        return round(($hits / $total) * 100, 2);
    }
}
