<?php

namespace App\Services\Demo;

use App\Models\SpeedwayRace;
use App\Services\MarketOddEstimatorService;

class DemoQuickEntryBuilder
{
    public function __construct(
        private readonly MarketOddEstimatorService $oddEstimator,
    ) {}

    /**
     * @param  array<string, mixed>  $calculated
     * @return list<array<string, mixed>>
     */
    public function build(SpeedwayRace $race, array $calculated = []): array
    {
        $pick = fn (string $key) => $race->getAttribute($key) ?? $calculated[$key] ?? null;
        $primary = [];
        $alternate = [];

        $rank1Position = $pick('rank_1_position');
        $rank1Odd = $pick('rank_1_odd');
        if ($rank1Position !== null && $rank1Odd !== null) {
            $primary[] = $this->winnerEntry(
                'winner_favorite',
                'Winner favorito',
                (int) $rank1Position,
                (float) $rank1Odd,
            );
        }

        $rank4Position = $pick('rank_4_position');
        $rank4Odd = $pick('rank_4_odd');
        if ($rank4Position !== null && $rank4Odd !== null) {
            $primary[] = $this->winnerEntry(
                'winner_underdog',
                'Winner zebra',
                (int) $rank4Position,
                (float) $rank4Odd,
            );
        }

        $suggestedForecast = $pick('market_rank_forecast_order');
        if (is_string($suggestedForecast) && $suggestedForecast !== '') {
            $primary[] = $this->forecastEntry(
                'forecast_suggested',
                "Forecast {$suggestedForecast}",
                $suggestedForecast,
                $race,
                true,
            );
        }

        $suggestedTricast = $pick('market_rank_tricast_order');
        if (is_string($suggestedTricast) && $suggestedTricast !== '') {
            $primary[] = $this->tricastEntry(
                'tricast_suggested',
                "Tricast {$suggestedTricast}",
                $suggestedTricast,
                $race,
                true,
            );
        }

        foreach ($this->alternateForecastOrders($pick, $suggestedForecast) as $order) {
            $alternate[] = $this->forecastEntry(
                'forecast_alternate_'.str_replace('-', '_', $order),
                "Forecast {$order}",
                $order,
                $race,
                false,
            );
        }

        return array_merge($primary, $alternate);
    }

    /**
     * @return list<string>
     */
    private function alternateForecastOrders(callable $pick, ?string $suggestedForecast): array
    {
        $candidates = array_filter([
            $this->orderFromRanks($pick, 1, 3),
            $this->orderFromRanks($pick, 2, 1),
            $this->orderFromRanks($pick, 1, 4),
            $this->orderFromRanks($pick, 3, 1),
        ]);

        $unique = [];
        foreach ($candidates as $order) {
            if ($order === $suggestedForecast || in_array($order, $unique, true)) {
                continue;
            }
            $unique[] = $order;
        }

        return $unique;
    }

    /**
     * @return array<string, mixed>
     */
    private function winnerEntry(string $id, string $label, int $position, float $odd): array
    {
        return [
            'id' => $id,
            'label' => $label,
            'tier' => 'primary',
            'market_type' => 'winner',
            'bet_type' => 'single',
            'order' => (string) $position,
            'entry_position' => $position,
            'entry_odd' => round($odd, 2),
            'pricing_status' => 'observed',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function forecastEntry(
        string $id,
        string $label,
        string $order,
        SpeedwayRace $race,
        bool $primary,
    ): array {
        $estimated = $this->oddEstimator->estimateForecastOdd($race, $order);

        return [
            'id' => $id,
            'label' => $label,
            'tier' => $primary ? 'primary' : 'alternate',
            'market_type' => 'forecast',
            'bet_type' => 'single',
            'order' => $order,
            'entry_odd' => $estimated,
            'pricing_status' => $estimated !== null ? 'estimated' : 'unavailable',
            'helper_text' => $estimated !== null
                ? 'Odd estimada. Altere se tiver a odd real.'
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tricastEntry(
        string $id,
        string $label,
        string $order,
        SpeedwayRace $race,
        bool $primary,
    ): array {
        $estimated = $this->oddEstimator->estimateTricastOdd($race, $order);

        return [
            'id' => $id,
            'label' => $label,
            'tier' => $primary ? 'primary' : 'alternate',
            'market_type' => 'tricast',
            'bet_type' => 'single',
            'order' => $order,
            'entry_odd' => $estimated,
            'pricing_status' => $estimated !== null ? 'estimated' : 'unavailable',
            'helper_text' => $estimated !== null
                ? 'Odd estimada. Altere se tiver a odd real.'
                : null,
        ];
    }

    /**
     * @param  callable(string): mixed  $pick
     */
    private function orderFromRanks(callable $pick, int $firstRank, int $secondRank): ?string
    {
        $first = $pick("rank_{$firstRank}_position");
        $second = $pick("rank_{$secondRank}_position");

        if ($first === null || $second === null) {
            return null;
        }

        return "{$first}-{$second}";
    }
}
