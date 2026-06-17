<?php

namespace App\Services\Speedway;

class SpeedwayParserService
{
    /**
     * @param  array<string, mixed>|list<mixed>  $payload
     * @return list<array<string, mixed>>
     */
    public function parseRacesFromPayload(array $payload): array
    {
        $races = $this->extractRaceArray($payload);

        return array_map(fn (array $race) => $this->mapRace($race), $races);
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $payload
     * @return array{race_count: int, pending_count: int, settled_count: int, races: list<array<string, mixed>>}
     */
    public function summarizePayload(array $payload): array
    {
        $races = $this->parseRacesFromPayload($payload);

        return [
            'race_count' => count($races),
            'pending_count' => count(array_filter($races, fn ($r) => $r['status'] === 'pending')),
            'settled_count' => count(array_filter($races, fn ($r) => $r['status'] === 'settled')),
            'races' => $races,
        ];
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function extractRaceArray(array $payload): array
    {
        if (array_is_list($payload)) {
            return array_values(array_filter($payload, fn ($item) => $this->isRaceObject($item)));
        }

        if (isset($payload['Linhas']) && is_array($payload['Linhas'])) {
            $races = [];
            foreach ($payload['Linhas'] as $linha) {
                if (! is_array($linha) || ! isset($linha['Colunas']) || ! is_array($linha['Colunas'])) {
                    continue;
                }
                foreach ($linha['Colunas'] as $coluna) {
                    if ($this->isRaceObject($coluna)) {
                        $races[] = $coluna;
                    }
                }
            }
            if ($races !== []) {
                return $races;
            }
        }

        foreach (['data', 'corridas', 'races', 'items', 'resultado', 'Resultado'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                $filtered = array_values(array_filter($payload[$key], fn ($item) => $this->isRaceObject($item)));
                if ($filtered !== []) {
                    return $filtered;
                }
            }
        }

        foreach ($payload as $value) {
            if (is_array($value) && array_is_list($value)) {
                $filtered = array_values(array_filter($value, fn ($item) => $this->isRaceObject($item)));
                if ($filtered !== []) {
                    return $filtered;
                }
            }
        }

        if ($this->isRaceObject($payload)) {
            return [$payload];
        }

        return [];
    }

    /**
     * @param  mixed  $value
     */
    private function isRaceObject($value): bool
    {
        if (! is_array($value) || array_is_list($value)) {
            return false;
        }

        $hasId = array_key_exists('Id', $value) || array_key_exists('id', $value);
        if (! $hasId) {
            return false;
        }

        $hasOdds = isset($value['Odds_Pilotos']) && $value['Odds_Pilotos'] !== '';
        $hasResult = isset($value['Vencedor']) && $value['Vencedor'] !== null && $value['Vencedor'] !== '';

        return $hasOdds || $hasResult;
    }

    /**
     * @param  array<string, mixed>  $race
     * @return array<string, mixed>
     */
    private function mapRace(array $race): array
    {
        $status = $this->classifyRace($race);

        return [
            'external_id' => (string) ($race['Id'] ?? $race['id']),
            'status' => $status,
            'race_hour' => isset($race['Hora']) ? (string) $race['Hora'] : null,
            'race_minute' => isset($race['Minutos']) ? (string) $race['Minutos'] : null,
            'pilot_odds_raw' => isset($race['Odds_Pilotos']) ? (string) $race['Odds_Pilotos'] : null,
            'winner_position' => $this->toIntOrNull($race['Vencedor'] ?? null),
            'winner_color' => isset($race['Cor_Vencedor']) ? (string) $race['Cor_Vencedor'] : null,
            'winner_odd' => $this->toDecimalOrNull($race['Odd'] ?? null),
            'pilot_name' => isset($race['Nome_Piloto']) ? (string) $race['Nome_Piloto'] : null,
            'prediction' => isset($race['Previsao']) ? (string) $race['Previsao'] : null,
            'prediction_odd' => $this->toDecimalOrNull($race['Odd_Previsao'] ?? null),
            'tricast_prediction' => isset($race['Previsao_Tricast']) ? (string) $race['Previsao_Tricast'] : null,
            'raw' => $race,
        ];
    }

    /**
     * @param  array<string, mixed>  $race
     */
    public function classifyRace(array $race): string
    {
        $hasWinner = isset($race['Vencedor']) && $race['Vencedor'] !== null && $race['Vencedor'] !== '';

        return $hasWinner ? 'settled' : 'pending';
    }

    private function toIntOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function toDecimalOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
