<?php

namespace App\Services\Speedway;

use App\Models\SpeedwayRace;

class RaceMetricsService
{
    /**
     * @param  SpeedwayRace|array<string, mixed>  $race
     * @return array<string, int|float|bool|null>
     */
    public function calculate(SpeedwayRace|array $race): array
    {
        $winnerPosition = $this->extractWinnerPosition($race);
        $odds = $this->extractPreRaceOdds($race);
        $metrics = $this->calculateFromOdds($odds);
        $rankByPosition = $this->rankByPosition($odds);
        $forecastPredictionPositions = $this->forecastPositions($odds);
        $actualForecastPositions = $this->extractActualForecastPositions($race);
        $tricastPredictionPositions = $this->tricastPositions($odds);
        $actualTricastPositions = $this->extractActualTricastPositions($race);

        $forecastHit = null;
        if (count($forecastPredictionPositions) >= 2 && count($actualForecastPositions) >= 2) {
            $forecastHit = array_slice($forecastPredictionPositions, 0, 2) === array_slice($actualForecastPositions, 0, 2);
        }

        $tricastWinnerHit = null;
        $tricastExactHit = null;
        if ($winnerPosition !== null && count($tricastPredictionPositions) >= 1) {
            $tricastWinnerHit = $tricastPredictionPositions[0] === $winnerPosition;
        }

        if (count($actualTricastPositions) >= 3 && count($tricastPredictionPositions) >= 3) {
            $tricastExactHit = array_slice($tricastPredictionPositions, 0, 3) === array_slice($actualTricastPositions, 0, 3);
        }

        return array_merge($metrics, [
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
    private function parsePredictionPositions(mixed $value): array
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
     * @param  list<array{position: int, odd: float}>  $odds
     * @return list<int>
     */
    private function forecastPositions(array $odds): array
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
    private function tricastPositions(array $odds): array
    {
        $rankedByOdds = $this->rankPositionsByOdds($odds);

        if (count($rankedByOdds) >= 3) {
            return array_slice($rankedByOdds, 0, 3);
        }

        return [];
    }

    /**
     * @return list<int>
     */
    private function extractActualForecastPositions(SpeedwayRace|array $race): array
    {
        $prediction = $this->parsePredictionPositions($this->extractValue($race, 'prediction'));
        if (count($prediction) >= 2) {
            return array_slice($prediction, 0, 2);
        }

        $rawResult = $this->extractValue($race, 'raw_result_payload');
        if (is_array($rawResult)) {
            foreach (['Previsao', 'Forecast_Result', 'Resultado_Forecast'] as $rawKey) {
                $positions = $this->parsePredictionPositions($rawResult[$rawKey] ?? null);
                if (count($positions) >= 2) {
                    return array_slice($positions, 0, 2);
                }
            }
        }

        return [];
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

    /**
     * @return list<int>
     */
    private function extractActualTricastPositions(SpeedwayRace|array $race): array
    {
        $prediction = $this->parsePredictionPositions($this->extractValue($race, 'tricast_prediction'));
        if (count($prediction) >= 3) {
            return array_slice($prediction, 0, 3);
        }

        $possibleKeys = [
            'resultado_tricast',
            'tricast_result',
            'tricast_result_raw',
        ];

        foreach ($possibleKeys as $key) {
            $positions = $this->parsePredictionPositions($this->extractValue($race, $key));
            if (count($positions) >= 3) {
                return $positions;
            }
        }

        $rawResult = $this->extractValue($race, 'raw_result_payload');
        if (is_array($rawResult)) {
            foreach ([
                'Previsao_Tricast',
                'Resultado_Tricast',
                'Tricast_Resultado',
                'Tricast_Result',
            ] as $rawKey) {
                $positions = $this->parsePredictionPositions($rawResult[$rawKey] ?? null);
                if (count($positions) >= 3) {
                    return $positions;
                }
            }
        }

        return [];
    }

    private function extractValue(SpeedwayRace|array $race, string $key): mixed
    {
        if ($race instanceof SpeedwayRace) {
            return $race->getAttribute($key);
        }

        return $race[$key] ?? null;
    }
}
