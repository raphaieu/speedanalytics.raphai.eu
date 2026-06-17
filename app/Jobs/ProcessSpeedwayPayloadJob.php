<?php

namespace App\Jobs;

use App\Models\SpeedwayPayload;
use App\Models\SpeedwayRace;
use App\Services\Speedway\SpeedwayParserService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Throwable;

class ProcessSpeedwayPayloadJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $speedwayPayloadId) {}

    public function handle(SpeedwayParserService $parser): void
    {
        $payloadRecord = SpeedwayPayload::query()->findOrFail($this->speedwayPayloadId);

        try {
            $summary = $parser->summarizePayload($payloadRecord->payload);
            $capturedAt = $payloadRecord->captured_at ?? now();

            foreach ($summary['races'] as $parsedRace) {
                $this->upsertRace($parsedRace, $capturedAt, $payloadRecord->id);
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
    private function upsertRace(array $parsedRace, Carbon $capturedAt, int $payloadId): void
    {
        $existing = SpeedwayRace::query()
            ->where('external_id', $parsedRace['external_id'])
            ->first();

        if (! $existing) {
            SpeedwayRace::query()->create([
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
            ]);

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

            if (! $existing->raw_pending_payload && $existing->pilot_odds_raw) {
                // Preserva odds pré-corrida já salvas no campo normalizado
            }
        }

        $existing->update($updates);
    }
}
