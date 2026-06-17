<?php

namespace App\Jobs;

use App\Models\SpeedwayPayload;
use App\Models\SpeedwayRace;
use App\Services\Speedway\RaceMetricsService;
use App\Services\Speedway\SpeedwayParserService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Throwable;

class ProcessSpeedwayPayloadJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $speedwayPayloadId) {}

    public function handle(SpeedwayParserService $parser, RaceMetricsService $metricsService): void
    {
        $payloadRecord = SpeedwayPayload::query()->findOrFail($this->speedwayPayloadId);

        try {
            $summary = $parser->summarizePayload($payloadRecord->payload);
            $capturedAt = $payloadRecord->captured_at ?? now();

            foreach ($summary['races'] as $parsedRace) {
                $this->upsertRace($parsedRace, $capturedAt, $payloadRecord->id, $metricsService);
            }

            $payloadRecord->update([
                'summary' => [
                    'race_count' => $summary['race_count'],
                    'pending_count' => $summary['pending_count'],
                    'settled_count' => $summary['settled_count'],
                ],
                'processing_status' => 'processed',
                'processed_at' => now(),
                'error_message' => null,
            ]);
        } catch (Throwable $exception) {
            $payloadRecord->update([
                'processing_status' => 'failed',
                'processed_at' => now(),
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $parsedRace
     */
    private function upsertRace(array $parsedRace, Carbon $capturedAt, int $payloadId, RaceMetricsService $metricsService): void
    {
        $externalId = (string) data_get($parsedRace, 'external_id', '');
        $existing = SpeedwayRace::query()->whereExternalId($externalId)->first();

        if (! $existing) {
            $createData = [
                'external_id' => $parsedRace['external_id'],
                'status' => $parsedRace['status'],
                'race_hour' => $parsedRace['race_hour'],
                'race_minute' => $parsedRace['race_minute'],
                'pilot_odds_raw' => $parsedRace['pilot_odds_raw'],
                'winner_position' => $parsedRace['status'] === 'settled' ? $parsedRace['winner_position'] : null,
                'winner_color' => $parsedRace['status'] === 'settled' ? $parsedRace['winner_color'] : null,
                'winner_odd' => $parsedRace['status'] === 'settled' ? $parsedRace['winner_odd'] : null,
                'pilot_name' => $parsedRace['status'] === 'settled' ? $parsedRace['pilot_name'] : null,
                'prediction' => $parsedRace['status'] === 'settled' ? $parsedRace['prediction'] : null,
                'prediction_odd' => $parsedRace['status'] === 'settled' ? $parsedRace['prediction_odd'] : null,
                'tricast_prediction' => $parsedRace['status'] === 'settled' ? $parsedRace['tricast_prediction'] : null,
                'first_seen_at' => $capturedAt,
                'settled_at' => $parsedRace['status'] === 'settled' ? $capturedAt : null,
                'raw_pending_payload' => $parsedRace['status'] === 'pending' ? $parsedRace['raw'] : null,
                'raw_result_payload' => $parsedRace['status'] === 'settled' ? $parsedRace['raw'] : null,
                'last_payload_id' => $payloadId,
            ];

            SpeedwayRace::query()->create(array_merge(
                $createData,
                $metricsService->calculate($createData),
            ));

            return;
        }

        $updates = [
            'status' => $parsedRace['status'],
            'race_hour' => $parsedRace['race_hour'] ?? $existing->race_hour,
            'race_minute' => $parsedRace['race_minute'] ?? $existing->race_minute,
            'last_payload_id' => $payloadId,
        ];

        if ($parsedRace['status'] === 'pending') {
            if (! $existing->raw_pending_payload) {
                $updates['raw_pending_payload'] = $parsedRace['raw'];
                $updates['pilot_odds_raw'] = $parsedRace['pilot_odds_raw'];
            }
        }

        if ($parsedRace['status'] === 'settled') {
            $updates['settled_at'] = $existing->settled_at ?? $capturedAt;
            $updates['raw_result_payload'] = $parsedRace['raw'];
            $updates['winner_position'] = $parsedRace['winner_position'];
            $updates['winner_color'] = $parsedRace['winner_color'];
            $updates['winner_odd'] = $parsedRace['winner_odd'];
            $updates['pilot_name'] = $parsedRace['pilot_name'];
            $updates['prediction'] = $parsedRace['prediction'];
            $updates['prediction_odd'] = $parsedRace['prediction_odd'];
            $updates['tricast_prediction'] = $parsedRace['tricast_prediction'];
        }

        $metricsInput = [
            'pilot_odds_raw' => $this->resolvePreRaceOddsRaw($existing, $parsedRace),
            'raw_pending_payload' => $existing->raw_pending_payload ?? $updates['raw_pending_payload'] ?? null,
            'winner_position' => $updates['winner_position'] ?? $existing->winner_position,
            'prediction' => $updates['prediction'] ?? $existing->prediction,
            'tricast_prediction' => $updates['tricast_prediction'] ?? $existing->tricast_prediction,
            'raw_result_payload' => $updates['raw_result_payload'] ?? $existing->raw_result_payload,
        ];

        $existing->update(array_merge(
            $updates,
            $metricsService->calculate($metricsInput),
        ));
    }

    /**
     * @param  array<string, mixed>  $parsedRace
     */
    private function resolvePreRaceOddsRaw(SpeedwayRace $existing, array $parsedRace): ?string
    {
        if (is_array($existing->raw_pending_payload)) {
            $pendingOdds = $existing->raw_pending_payload['Odds_Pilotos'] ?? null;
            if (is_string($pendingOdds) && $pendingOdds !== '') {
                return $pendingOdds;
            }
        }

        if (is_string($existing->pilot_odds_raw) && $existing->pilot_odds_raw !== '') {
            return $existing->pilot_odds_raw;
        }

        if (isset($parsedRace['raw']) && is_array($parsedRace['raw'])) {
            $pendingOdds = $parsedRace['raw']['Odds_Pilotos'] ?? null;
            if (is_string($pendingOdds) && $pendingOdds !== '') {
                return $pendingOdds;
            }
        }

        return isset($parsedRace['pilot_odds_raw']) && is_string($parsedRace['pilot_odds_raw']) && $parsedRace['pilot_odds_raw'] !== ''
            ? $parsedRace['pilot_odds_raw']
            : null;
    }
}
