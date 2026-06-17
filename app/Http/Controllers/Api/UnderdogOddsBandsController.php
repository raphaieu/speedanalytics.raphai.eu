<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SpeedwayRace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class UnderdogOddsBandsController extends Controller
{
    private const TIMEZONE = 'America/Sao_Paulo';

    /**
     * @var array<int, array{label: string, min: float, max: float|null}>
     */
    private const BANDS = [
        ['label' => '5.00-5.99', 'min' => 5.00, 'max' => 5.99],
        ['label' => '6.00-7.99', 'min' => 6.00, 'max' => 7.99],
        ['label' => '8.00-9.99', 'min' => 8.00, 'max' => 9.99],
        ['label' => '10.00-11.99', 'min' => 10.00, 'max' => 11.99],
        ['label' => '12.00-14.99', 'min' => 12.00, 'max' => 14.99],
        ['label' => '15.00+', 'min' => 15.00, 'max' => null],
    ];

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'hour_from' => ['nullable', 'integer', 'min:0', 'max:23'],
            'hour_to' => ['nullable', 'integer', 'min:0', 'max:23'],
            'only_validated' => ['nullable', 'in:1,0,true,false'],
        ]);

        $onlyValidated = $request->has('only_validated')
            ? $request->boolean('only_validated')
            : true;

        $statusColumn = 'status';
        $underdogOddColumn = 'underdog_odd';
        $winnerUnderdogColumn = 'winner_was_underdog';

        $scope = SpeedwayRace::query()
            ->where($statusColumn, 'settled')
            ->whereNotNull($underdogOddColumn)
            ->whereNotNull($winnerUnderdogColumn);

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
        $profitableBands = 0;
        $bestBand = null;
        $worstBand = null;

        foreach (self::BANDS as $band) {
            $bandScope = clone $scope;

            if ($band['max'] === null) {
                $bandScope->where($underdogOddColumn, '>=', $band['min']);
            } else {
                $bandScope
                    ->where($underdogOddColumn, '>=', $band['min'])
                    ->where($underdogOddColumn, '<=', $band['max']);
            }

            $total = (int) (clone $bandScope)->count();
            $wins = (int) (clone $bandScope)->where($winnerUnderdogColumn, true)->count();
            $losses = $total - $wins;
            $averageUnderdogOdd = (float) ((clone $bandScope)->avg($underdogOddColumn) ?? 0.0);
            $sumInverseOdds = (float) ((clone $bandScope)->selectRaw('COALESCE(SUM(1.0 / underdog_odd), 0) as value')->value('value') ?? 0.0);
            $sumWinningOdds = (float) ((clone $bandScope)->where($winnerUnderdogColumn, true)->sum($underdogOddColumn));
            $profitLoss = $sumWinningOdds - $wins - $losses;

            $winRateDecimal = $total > 0 ? ($wins / $total) : 0.0;
            $impliedProbabilityDecimal = $total > 0 ? ($sumInverseOdds / $total) : 0.0;
            $edgeDecimal = $winRateDecimal - $impliedProbabilityDecimal;
            $roiDecimal = $total > 0 ? ($profitLoss / $total) : 0.0;

            $item = [
                'band' => $band['label'],
                'min' => $band['min'],
                'max' => $band['max'],
                'total' => $total,
                'wins' => $wins,
                'losses' => $losses,
                'win_rate' => round($winRateDecimal * 100, 2),
                'average_underdog_odd' => round($averageUnderdogOdd, 2),
                'implied_probability' => round($impliedProbabilityDecimal * 100, 2),
                'edge_vs_implied' => round($edgeDecimal * 100, 2),
                'profit_loss' => round($profitLoss, 2),
                'theoretical_roi' => round($roiDecimal * 100, 2),
            ];

            if ($total > 0 && $item['theoretical_roi'] > 0) {
                $profitableBands++;
            }

            if ($total > 0 && ($bestBand === null || $item['theoretical_roi'] > $bestBand['theoretical_roi'])) {
                $bestBand = $item;
            }

            if ($total > 0 && ($worstBand === null || $item['theoretical_roi'] < $worstBand['theoretical_roi'])) {
                $worstBand = $item;
            }

            $bands[] = $item;
        }

        return response()->json([
            'filters' => [
                'date_from' => $validated['date_from'] ?? null,
                'date_to' => $validated['date_to'] ?? null,
                'hour_from' => $validated['hour_from'] ?? null,
                'hour_to' => $validated['hour_to'] ?? null,
                'only_validated' => $onlyValidated,
                'status' => 'settled',
            ],
            'bands' => $bands,
            'summary' => [
                'total_races' => (int) (clone $scope)->count(),
                'profitable_bands' => $profitableBands,
                'best_band' => $bestBand,
                'worst_band' => $worstBand,
            ],
            'metadata' => [
                'percentage_format' => 'percentage_points_in_api',
            ],
        ]);
    }
}
