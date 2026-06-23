<?php

namespace App\Support;

use App\Models\SpeedwayRace;
use App\Services\Speedway\RaceTimingService;

class SpeedwayRacePresenter
{
    /**
     * @param  array<string, mixed>  $calculated  métricas calculadas (fallback quando colunas ainda não persistidas)
     * @return array<string, mixed>
     */
    public static function pendingForDemo(
        SpeedwayRace $race,
        array $calculated = [],
        ?int $maxPendingExternalId = null,
    ): array {
        $oddsRaw = self::pendingOddsRaw($race) ?? $race->pilot_odds_raw;
        $pilots = self::pilots($oddsRaw, $race);
        $pick = fn (string $key) => $race->getAttribute($key) ?? $calculated[$key] ?? null;

        return array_merge(
            [
                'id' => $race->id,
                'external_id' => $race->external_id,
                'status' => $race->status,
                'schedule_slot' => self::scheduleSlot($race),
                'race_hour' => $race->race_hour,
                'race_minute' => $race->race_minute,
                'pilot_odds_raw' => $oddsRaw,
                'pilot_odds' => array_map(
                    fn (array $pilot) => ['position' => $pilot['position'], 'odd' => $pilot['odd']],
                    $pilots,
                ),
                'rank_1_position' => $pick('rank_1_position'),
                'rank_1_odd' => $pick('rank_1_odd'),
                'rank_2_position' => $pick('rank_2_position'),
                'rank_2_odd' => $pick('rank_2_odd'),
                'rank_3_position' => $pick('rank_3_position'),
                'rank_3_odd' => $pick('rank_3_odd'),
                'rank_4_position' => $pick('rank_4_position'),
                'rank_4_odd' => $pick('rank_4_odd'),
                'favorite_position' => $pick('favorite_position'),
                'favorite_odd' => $pick('favorite_odd'),
                'underdog_position' => $pick('underdog_position'),
                'underdog_odd' => $pick('underdog_odd'),
                'market_rank_forecast_order' => $pick('market_rank_forecast_order'),
                'market_rank_tricast_order' => $pick('market_rank_tricast_order'),
            ],
            app(RaceTimingService::class)->presentation($race, null, $maxPendingExternalId),
        );
    }

    /**
     * @param  array<string, mixed>  $calculated
     * @return array<string, mixed>
     */
    public static function contextSnapshotForDemo(SpeedwayRace $race, array $calculated = []): array
    {
        return array_merge(self::pendingForDemo($race, $calculated), [
            'source' => 'demo_manual_pending_picker',
            'captured_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function summary(SpeedwayRace $race, ?int $maxPendingExternalId = null): array
    {
        $oddsForForecast = self::oddsForForecast($race);
        $oddsAnalysis = SpeedwayOddsForecast::analyze(
            $oddsForForecast,
            $race->status === 'settled' ? $race->winner_position : null,
        );

        $timing = $race->status === 'pending'
            ? app(RaceTimingService::class)->presentation($race, null, $maxPendingExternalId)
            : null;

        $preRaceOdds = self::oddsForForecast($race);

        return [
            'id' => $race->id,
            'external_id' => $race->external_id,
            'status' => $race->status,
            'schedule_slot' => self::scheduleSlot($race),
            'race_hour' => $race->race_hour,
            'race_minute' => $race->race_minute,
            'pilot_odds_raw' => $preRaceOdds,
            'favorite_position' => $oddsAnalysis['favorite_position'],
            'favorite_odd' => $race->favorite_odd !== null ? (string) $race->favorite_odd : null,
            'underdog_position' => $race->underdog_position,
            'underdog_odd' => $race->underdog_odd !== null ? (string) $race->underdog_odd : null,
            'odds_forecast' => $oddsAnalysis['forecast'],
            'odds_tricast' => $oddsAnalysis['tricast'],
            'favorite_won' => $oddsAnalysis['favorite_won'],
            'underdog_won' => $race->winner_was_underdog,
            'forecast_hit' => $race->forecast_hit,
            'tricast_exact_hit' => $race->tricast_exact_hit,
            'forecast_first_won' => $oddsAnalysis['forecast_first_won'],
            'result_forecast_order' => $race->result_forecast_order,
            'result_tricast_order' => $race->result_tricast_order,
            'winner_position' => $race->winner_position,
            'winner_odd' => $race->winner_odd,
            'pilot_name' => $race->pilot_name,
            'first_seen_at' => $race->first_seen_at?->toIso8601String(),
            'settled_at' => $race->settled_at?->toIso8601String(),
            'stale_at' => $race->stale_at?->toIso8601String(),
            'stale_reason' => $race->stale_reason,
            'timing' => $timing,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function detail(SpeedwayRace $race): array
    {
        $summary = self::summary($race);
        $pendingOdds = self::pendingOddsRaw($race);
        $oddsForForecast = $pendingOdds ?? $race->pilot_odds_raw;
        $oddsAnalysis = SpeedwayOddsForecast::analyze(
            $oddsForForecast,
            $race->status === 'settled' ? $race->winner_position : null,
        );

        return array_merge($summary, [
            'odds_analysis' => $oddsAnalysis,
            'pilots' => self::pilots($race->pilot_odds_raw, $race),
            'pending_pilots' => $pendingOdds ? self::pilots($pendingOdds, $race) : null,
            'timeline' => [
                'first_seen_at' => $race->first_seen_at?->toIso8601String(),
                'settled_at' => $race->settled_at?->toIso8601String(),
                'has_pending_snapshot' => $race->raw_pending_payload !== null,
            ],
            'raw_pending_payload' => $race->raw_pending_payload,
            'raw_result_payload' => $race->raw_result_payload,
        ]);
    }

    private static function oddsForForecast(SpeedwayRace $race): ?string
    {
        return self::pendingOddsRaw($race) ?? $race->pilot_odds_raw;
    }

    private static function scheduleSlot(SpeedwayRace $race): ?string
    {
        if ($race->race_hour === null || $race->race_minute === null) {
            return null;
        }

        return "{$race->race_hour}h".str_pad((string) $race->race_minute, 2, '0', STR_PAD_LEFT);
    }

    private static function pendingOddsRaw(SpeedwayRace $race): ?string
    {
        $pending = $race->raw_pending_payload;

        if (! is_array($pending)) {
            return null;
        }

        $odds = $pending['Odds_Pilotos'] ?? null;

        return is_string($odds) && $odds !== '' ? $odds : null;
    }

    /**
     * @return list<array{position: int, odd: string, color: string, is_favorite: bool, is_winner: bool}>
     */
    private static function pilots(?string $oddsRaw, SpeedwayRace $race): array
    {
        if (! $oddsRaw) {
            return [];
        }

        $parts = array_values(array_filter(array_map('trim', explode('|', $oddsRaw)), fn ($v) => $v !== ''));
        $favorite = SpeedwayOddsForecast::favoritePosition($oddsRaw);
        $colors = ['Verde', 'Vermelho', 'Amarelo', 'Roxo'];
        $pilots = [];

        foreach ($parts as $index => $odd) {
            $position = $index + 1;
            $pilots[] = [
                'position' => $position,
                'odd' => $odd,
                'color' => $colors[$index] ?? 'Cinza',
                'is_favorite' => $favorite === $position,
                'is_winner' => $race->winner_position === $position,
            ];
        }

        return $pilots;
    }
}
