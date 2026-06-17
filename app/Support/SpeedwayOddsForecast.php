<?php

namespace App\Support;

class SpeedwayOddsForecast
{
    /**
     * @return list<int>
     */
    public static function rankByOdds(?string $oddsRaw): array
    {
        if (! $oddsRaw) {
            return [];
        }

        $parts = array_values(array_filter(
            array_map('trim', explode('|', $oddsRaw)),
            fn ($v) => $v !== '',
        ));

        if ($parts === []) {
            return [];
        }

        $positions = range(1, count($parts));

        usort($positions, function (int $a, int $b) use ($parts) {
            $oddA = (float) $parts[$a - 1];
            $oddB = (float) $parts[$b - 1];

            return $oddA <=> $oddB ?: $a <=> $b;
        });

        return $positions;
    }

    public static function favoritePosition(?string $oddsRaw): ?int
    {
        $ranked = self::rankByOdds($oddsRaw);

        return $ranked[0] ?? null;
    }

    public static function forecast(?string $oddsRaw): ?string
    {
        $ranked = self::rankByOdds($oddsRaw);

        if (count($ranked) < 2) {
            return null;
        }

        return "{$ranked[0]}-{$ranked[1]}";
    }

    public static function tricast(?string $oddsRaw): ?string
    {
        $ranked = self::rankByOdds($oddsRaw);

        if (count($ranked) < 3) {
            return null;
        }

        return implode('-', array_slice($ranked, 0, 3));
    }

    /**
     * @return array{forecast: ?string, tricast: ?string, favorite_position: ?int, ranked: list<int>}
     */
    public static function analyze(?string $oddsRaw, ?int $winnerPosition = null): array
    {
        $ranked = self::rankByOdds($oddsRaw);
        $favorite = $ranked[0] ?? null;
        $forecast = self::forecast($oddsRaw);
        $forecastFirst = $ranked[0] ?? null;

        return [
            'forecast' => $forecast,
            'tricast' => self::tricast($oddsRaw),
            'favorite_position' => $favorite,
            'ranked' => $ranked,
            'favorite_won' => $winnerPosition && $favorite
                ? $winnerPosition === $favorite
                : null,
            'forecast_first_won' => $winnerPosition && $forecastFirst
                ? $winnerPosition === $forecastFirst
                : null,
        ];
    }
}
