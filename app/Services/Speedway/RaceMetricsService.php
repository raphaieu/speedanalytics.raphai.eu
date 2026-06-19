<?php

namespace App\Services\Speedway;

use App\Models\SpeedwayRace;

class RaceMetricsService
{
    /**
     * @param  SpeedwayRace|array<string, mixed>  $race
     * @return array<string, int|float|bool|string|null>
     */
    public function calculate(SpeedwayRace|array $race): array
    {
        $winnerPosition = $this->extractWinnerPosition($race);
        $odds = $this->extractPreRaceOdds($race);
        $metrics = $this->calculateFromOdds($odds);
        $rankColumns = $this->calculateOddsRankColumns($odds);
        $rankByPosition = $this->rankByPosition($odds);
        $marketForecastPositions = $this->marketRankForecastPositions($odds);
        $marketTricastPositions = $this->marketRankTricastPositions($odds);
        $resultForecastOrder = $this->extractResultForecastOrder($race);
        $resultForecastOdd = $this->extractResultForecastOdd($race);
        $resultTricastOrder = $this->extractResultTricastOrder($race);
        $resultForecastPositions = $this->parseOrderPositions($resultForecastOrder);
        $resultTricastPositions = $this->parseOrderPositions($resultTricastOrder);

        $forecastHit = null;
        if (count($marketForecastPositions) >= 2 && count($resultForecastPositions) >= 2) {
            $forecastHit = array_slice($marketForecastPositions, 0, 2) === array_slice($resultForecastPositions, 0, 2);
        }

        $tricastWinnerHit = null;
        $tricastExactHit = null;
        if ($winnerPosition !== null && count($marketTricastPositions) >= 1) {
            $tricastWinnerHit = $marketTricastPositions[0] === $winnerPosition;
        }

        if (count($resultTricastPositions) >= 3 && count($marketTricastPositions) >= 3) {
            $tricastExactHit = array_slice($marketTricastPositions, 0, 3) === array_slice($resultTricastPositions, 0, 3);
        }

        return array_merge($metrics, $rankColumns, [
            'market_rank_forecast_order' => $this->formatOrder($marketForecastPositions, 2),
            'market_rank_tricast_order' => $this->formatOrder($marketTricastPositions, 3),
            'result_forecast_order' => $resultForecastOrder,
            'result_forecast_odd' => $resultForecastOdd,
            'result_tricast_order' => $resultTricastOrder,
            'winner_was_favorite' => $winnerPosition !== null && $metrics['favorite_position'] !== null
                ? $winnerPosition === $metrics['favorite_position']
                : null,
            'winner_was_underdog' => $winnerPosition !== null && $metrics['underdog_position'] !== null
                ? $winnerPosition === $metrics['underdog_position']
                : null,
            'winner_odd_rank' => $winnerPosition !== null ? ($rankByPosition[$winnerPosition] ?? null) : null,
            'forecast_hit' => $forecastHit,
            // Mantido por retrocompatibilidade: agora representa acerto exato.
            'tricast_hit' => $tricastExactHit,
            'tricast_winner_hit' => $tricastWinnerHit,
            'tricast_exact_hit' => $tricastExactHit,
        ]);
    }

    /**
     * @param  list<array{position: int, odd: float}>  $odds
     * @return array<string, int|float|null>
     */
    private function calculateFromOdds(array $odds): array
    {
        if ($odds === []) {
            return [
                'favorite_position' => null,
                'favorite_odd' => null,
                'second_favorite_position' => null,
                'second_favorite_odd' => null,
                'underdog_position' => null,
                'underdog_odd' => null,
                'odds_spread' => null,
                'house_margin' => null,
            ];
        }

        usort($odds, function (array $a, array $b): int {
            return $a['odd'] <=> $b['odd'] ?: $a['position'] <=> $b['position'];
        });

        $favorite = $odds[0] ?? null;
        $secondFavorite = $odds[1] ?? null;
        $underdog = $this->resolveUnderdog($odds);
        $minOdd = $favorite['odd'] ?? null;
        $maxOdd = $underdog['odd'] ?? null;

        $inverseSum = 0.0;
        foreach ($odds as $entry) {
            if ($entry['odd'] <= 0.0) {
                continue;
            }
            $inverseSum += 1 / $entry['odd'];
        }

        return [
            'favorite_position' => $favorite['position'] ?? null,
            'favorite_odd' => $favorite['odd'] ?? null,
            'second_favorite_position' => $secondFavorite['position'] ?? null,
            'second_favorite_odd' => $secondFavorite['odd'] ?? null,
            'underdog_position' => $underdog['position'] ?? null,
            'underdog_odd' => $underdog['odd'] ?? null,
            'odds_spread' => $minOdd !== null && $maxOdd !== null ? ($maxOdd - $minOdd) : null,
            'house_margin' => $inverseSum > 0 ? ($inverseSum - 1) : null,
        ];
    }

    /**
     * @param  list<array{position: int, odd: float}>  $odds
     * @return array<string, int|float|null>
     */
    private function calculateOddsRankColumns(array $odds): array
    {
        $sorted = $odds;

        usort($sorted, function (array $a, array $b): int {
            return $a['odd'] <=> $b['odd'] ?: $a['position'] <=> $b['position'];
        });

        $columns = [];

        for ($rank = 1; $rank <= 4; $rank++) {
            $entry = $sorted[$rank - 1] ?? null;
            $columns["rank_{$rank}_position"] = $entry['position'] ?? null;
            $columns["rank_{$rank}_odd"] = $entry['odd'] ?? null;
        }

        return $columns;
    }

    /**
     * @param  list<array{position: int, odd: float}>  $orderedOdds
     * @return array{position: int, odd: float}|null
     */
    private function resolveUnderdog(array $orderedOdds): ?array
    {
        if ($orderedOdds === []) {
            return null;
        }

        $maxOdd = max(array_column($orderedOdds, 'odd'));
        foreach ($orderedOdds as $entry) {
            if ($entry['odd'] === $maxOdd) {
                return $entry;
            }
        }

        return null;
    }

    private function extractWinnerPosition(SpeedwayRace|array $race): ?int
    {
        $winner = $this->extractValue($race, 'winner_position');
        if ($winner === null || $winner === '') {
            return null;
        }

        return (int) $winner;
    }

    /**
     * @return list<array{position: int, odd: float}>
     */
    private function extractPreRaceOdds(SpeedwayRace|array $race): array
    {
        $oddsRaw = null;

        if ($race instanceof SpeedwayRace) {
            $pendingOdds = is_array($race->raw_pending_payload)
                ? ($race->raw_pending_payload['Odds_Pilotos'] ?? null)
                : null;

            $oddsRaw = is_string($pendingOdds) && $pendingOdds !== ''
                ? $pendingOdds
                : $race->pilot_odds_raw;
        } else {
            $pending = $race['raw_pending_payload'] ?? null;
            $pendingOdds = is_array($pending) ? ($pending['Odds_Pilotos'] ?? null) : null;
            $oddsRaw = is_string($pendingOdds) && $pendingOdds !== ''
                ? $pendingOdds
                : ($race['pilot_odds_raw'] ?? null);
        }

        if (! is_string($oddsRaw) || trim($oddsRaw) === '') {
            return [];
        }

        $parts = array_values(array_filter(array_map('trim', explode('|', $oddsRaw)), fn ($value) => $value !== ''));
        $result = [];

        foreach ($parts as $index => $oddRaw) {
            $odd = (float) $oddRaw;
            if ($odd <= 0.0) {
                continue;
            }

            $result[] = [
                'position' => $index + 1,
                'odd' => $odd,
            ];
        }

        return $result;
    }

    /**
     * @return list<int>
     */
    private function parseOrderPositions(mixed $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        preg_match_all('/\d+/', $value, $matches);
        if (! isset($matches[0]) || ! is_array($matches[0])) {
            return [];
        }

        return array_map(fn (string $item) => (int) $item, $matches[0]);
    }

    /**
     * @param  list<int>  $positions
     */
    private function formatOrder(array $positions, int $minimumCount): ?string
    {
        if (count($positions) < $minimumCount) {
            return null;
        }

        return implode('-', array_slice($positions, 0, $minimumCount));
    }

    /**
     * @param  list<array{position: int, odd: float}>  $odds
     * @return list<int>
     */
    private function marketRankForecastPositions(array $odds): array
    {
        $rankedByOdds = $this->rankPositionsByOdds($odds);

        if (count($rankedByOdds) >= 2) {
            return array_slice($rankedByOdds, 0, 2);
        }

        return [];
    }

    /**
     * @param  list<array{position: int, odd: float}>  $odds
     * @return list<int>
     */
    private function marketRankTricastPositions(array $odds): array
    {
        $rankedByOdds = $this->rankPositionsByOdds($odds);

        if (count($rankedByOdds) >= 3) {
            return array_slice($rankedByOdds, 0, 3);
        }

        return [];
    }

    private function extractResultForecastOrder(SpeedwayRace|array $race): ?string
    {
        $rawResult = $this->extractValue($race, 'raw_result_payload');
        if (! is_array($rawResult)) {
            return null;
        }

        $value = $rawResult['Previsao'] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function extractResultForecastOdd(SpeedwayRace|array $race): ?float
    {
        $rawResult = $this->extractValue($race, 'raw_result_payload');
        if (! is_array($rawResult)) {
            return null;
        }

        $value = $rawResult['Odd_Previsao'] ?? null;
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private function extractResultTricastOrder(SpeedwayRace|array $race): ?string
    {
        $rawResult = $this->extractValue($race, 'raw_result_payload');
        if (! is_array($rawResult)) {
            return null;
        }

        $value = $rawResult['Previsao_Tricast'] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * @param  list<array{position: int, odd: float}>  $odds
     * @return list<int>
     */
    private function rankPositionsByOdds(array $odds): array
    {
        $sorted = $odds;

        usort($sorted, function (array $a, array $b): int {
            return $a['odd'] <=> $b['odd'] ?: $a['position'] <=> $b['position'];
        });

        return array_values(array_map(
            fn (array $entry) => (int) $entry['position'],
            $sorted
        ));
    }

    /**
     * @param  list<array{position: int, odd: float}>  $odds
     * @return array<int, int>
     */
    private function rankByPosition(array $odds): array
    {
        $rankedPositions = $this->rankPositionsByOdds($odds);
        $ranks = [];

        foreach ($rankedPositions as $index => $position) {
            $ranks[$position] = $index + 1;
        }

        return $ranks;
    }

    private function extractValue(SpeedwayRace|array $race, string $key): mixed
    {
        if ($race instanceof SpeedwayRace) {
            return $race->getAttribute($key);
        }

        return $race[$key] ?? null;
    }
}
