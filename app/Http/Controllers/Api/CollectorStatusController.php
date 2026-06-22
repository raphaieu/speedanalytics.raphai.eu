<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SpeedwayRace;
use App\Services\Speedway\RaceTimingService;
use App\Support\SpeedwayRacePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

class CollectorStatusController extends Controller
{
    public function show(): JsonResponse
    {
        $path = config('speedway.collector_status_path');
        $staleThresholdSeconds = (int) config('speedway.collector_payload_stale_seconds', 120);

        if (! File::isFile($path)) {
            return response()->json([
                'status' => 'unknown',
                'effective_status' => 'unknown',
                'message' => 'Arquivo de status do collector não encontrado',
                'path' => $path,
                'needs_login' => null,
                'last_payload_at' => null,
                'last_external_id' => null,
                'payload_age_seconds' => null,
                'is_payload_stale' => true,
            ]);
        }

        $data = json_decode(File::get($path), true);

        if (! is_array($data)) {
            return response()->json([
                'status' => 'error',
                'effective_status' => 'error',
                'message' => 'Status do collector inválido',
            ], 500);
        }

        $payloadAgeSeconds = null;
        $isPayloadStale = false;

        if (! empty($data['last_payload_at'])) {
            $payloadAgeSeconds = (int) now()->diffInSeconds(Carbon::parse($data['last_payload_at']));
            $isPayloadStale = $payloadAgeSeconds > $staleThresholdSeconds;
        } elseif (($data['status'] ?? null) === 'running') {
            $isPayloadStale = true;
        }

        $rawStatus = $data['status'] ?? 'unknown';
        $effectiveStatus = $rawStatus;

        if ($isPayloadStale && in_array($rawStatus, ['valid', 'running'], true)) {
            $effectiveStatus = 'stale';
        }

        return response()->json(array_merge($data, [
            'payload_age_seconds' => $payloadAgeSeconds,
            'payload_stale_threshold_seconds' => $staleThresholdSeconds,
            'is_payload_stale' => $isPayloadStale,
            'effective_status' => $effectiveStatus,
        ]));
    }
}
