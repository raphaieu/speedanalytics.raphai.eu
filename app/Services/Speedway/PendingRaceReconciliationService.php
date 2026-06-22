<?php

namespace App\Services\Speedway;

use App\Jobs\ProcessSpeedwayPayloadJob;
use App\Models\SpeedwayPayload;
use App\Models\SpeedwayRace;
use Illuminate\Support\Carbon;

class PendingRaceReconciliationService
{
    public function __construct(
        private readonly RaceTimingService $timingService,
        private readonly SpeedwayParserService $parser,
    ) {}

    /**
     * @return array{checked: int, settled: int, marked_stale: int}
     */
    public function reconcile(?Carbon $now = null): array
    {
        $now ??= now();
        $stats = ['checked' => 0, 'settled' => 0, 'marked_stale' => 0];

        $allPending = SpeedwayRace::query()
            ->where('status', 'pending')
            ->orderBy('external_id')
            ->get();

        $maxPendingExternalId = $this->timingService->maxPendingExternalId($allPending);

        $candidates = $allPending->filter(
            fn (SpeedwayRace $race) => $this->timingService->analyze($race, $now, $maxPendingExternalId)['is_stale'],
        );

        foreach ($candidates as $race) {
            $stats['checked']++;

            $payload = $this->findSettledPayloadForRace($race->external_id);

            if ($payload !== null) {
                ProcessSpeedwayPayloadJob::dispatchSync($payload->id);
                $race->refresh();

                if ($race->status === 'settled') {
                    $stats['settled']++;

                    continue;
                }
            }

            if ($race->status === 'pending' && $race->stale_at === null) {
                $race->update([
                    'stale_at' => $now,
                    'stale_reason' => $payload === null ? 'collection_gap' : 'reconcile_failed',
                ]);
                $stats['marked_stale']++;
            }
        }

        return $stats;
    }

    private function findSettledPayloadForRace(string $externalId): ?SpeedwayPayload
    {
        $lookbackHours = (int) config('speedway.reconciliation_payload_lookback_hours', 24);

        $payloads = SpeedwayPayload::query()
            ->where('captured_at', '>=', now()->subHours($lookbackHours))
            ->orderByDesc('captured_at')
            ->limit((int) config('speedway.reconciliation_payload_scan_limit', 200))
            ->get();

        foreach ($payloads as $payload) {
            if (! is_array($payload->payload)) {
                continue;
            }

            $summary = $this->parser->summarizePayload($payload->payload);

            foreach ($summary['races'] as $parsedRace) {
                if (($parsedRace['external_id'] ?? null) === $externalId && ($parsedRace['status'] ?? null) === 'settled') {
                    return $payload;
                }
            }
        }

        return null;
    }
}
