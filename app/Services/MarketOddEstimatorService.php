<?php

namespace App\Services;

use App\Models\SpeedwayRace;

/**
 * Heurística provisória para odds de Forecast/Tricast.
 * Multiplicadores configuráveis — calibrar depois com odds reais observadas.
 */
class MarketOddEstimatorService
{
    public function estimateForecastOdd(SpeedwayRace|array $race, string $order): ?float
    {
        $positions = $this->parseOrder($order);
        if (count($positions) !== 2) {
            return null;
        }

        $product = $this->productOfPilotOdds($race, $positions);
        if ($product === null) {
            return null;
        }

        $multiplier = (float) config('speedway.market_odd_estimation.forecast_product_multiplier', 0.65);

        return round($product * $multiplier, 2);
    }

    public function estimateTricastOdd(SpeedwayRace|array $race, string $order): ?float
    {
        $positions = $this->parseOrder($order);
        if (count($positions) !== 3) {
            return null;
        }

        $product = $this->productOfPilotOdds($race, $positions);
        if ($product === null) {
            return null;
        }

        $multiplier = (float) config('speedway.market_odd_estimation.tricast_product_multiplier', 0.35);

        return round($product * $multiplier, 2);
    }

    /**
     * @return list<int>
     */
    private function parseOrder(string $order): array
    {
        $trimmed = trim($order);
        if ($trimmed === '') {
            return [];
        }

        if (! preg_match_all('/\d+/', $trimmed, $matches)) {
            return [];
        }

        return array_map('intval', $matches[0]);
    }

    /**
     * @param  list<int>  $positions
     */
    private function productOfPilotOdds(SpeedwayRace|array $race, array $positions): ?float
    {
        $oddsByPosition = $this->pilotOddsByPosition($race);
        if ($oddsByPosition === null) {
            return null;
        }

        $product = 1.0;

        foreach ($positions as $position) {
            $odd = $oddsByPosition[$position] ?? null;
            if ($odd === null || $odd <= 0) {
                return null;
            }

            $product *= $odd;
        }

        return $product;
    }

    /**
     * @return array<int, float>|null position => odd
     */
    private function pilotOddsByPosition(SpeedwayRace|array $race): ?array
    {
        $raw = $this->extractOddsRaw($race);
        if ($raw === null) {
            return null;
        }

        $parts = array_values(array_filter(array_map('trim', explode('|', $raw)), fn ($v) => $v !== ''));
        if ($parts === []) {
            return null;
        }

        $map = [];
        foreach ($parts as $index => $odd) {
            $map[$index + 1] = (float) $odd;
        }

        return $map;
    }

    private function extractOddsRaw(SpeedwayRace|array $race): ?string
    {
        if ($race instanceof SpeedwayRace) {
            $pending = $race->raw_pending_payload;
            if (is_array($pending)) {
                $odds = $pending['Odds_Pilotos'] ?? null;
                if (is_string($odds) && trim($odds) !== '') {
                    return trim($odds);
                }
            }

            $raw = $race->pilot_odds_raw;

            return is_string($raw) && trim($raw) !== '' ? trim($raw) : null;
        }

        $pending = $race['raw_pending_payload'] ?? null;
        if (is_array($pending)) {
            $odds = $pending['Odds_Pilotos'] ?? null;
            if (is_string($odds) && trim($odds) !== '') {
                return trim($odds);
            }
        }

        $raw = $race['pilot_odds_raw'] ?? null;

        return is_string($raw) && trim($raw) !== '' ? trim($raw) : null;
    }
}
