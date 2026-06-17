<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessSpeedwayPayloadJob;
use App\Models\SpeedwayPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CollectorIngestController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $token = $request->header('x-speedway-collector-token');
        $expected = config('speedway.collector_token');

        if (! $expected || ! hash_equals($expected, (string) $token)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'source' => ['required', 'string', 'max:32'],
            'mode' => ['nullable', 'string', 'max:64'],
            'source_url' => ['nullable', 'string'],
            'captured_at' => ['required', 'date'],
            'data_atualizacao' => ['nullable', 'string', 'max:64'],
            'payload' => ['required', 'array'],
            'summary' => ['nullable', 'array'],
        ]);

        $payloadRecord = SpeedwayPayload::query()->create([
            'source' => $validated['source'],
            'mode' => $validated['mode'] ?? null,
            'source_url' => $validated['source_url'] ?? null,
            'captured_at' => Carbon::parse($validated['captured_at']),
            'data_atualizacao' => $validated['data_atualizacao'] ?? null,
            'payload' => $validated['payload'],
            'summary' => $validated['summary'] ?? null,
            'processing_status' => 'pending',
        ]);

        ProcessSpeedwayPayloadJob::dispatch($payloadRecord->id);

        return response()->json([
            'message' => 'Payload aceito para processamento',
            'accepted' => true,
            'payload_id' => $payloadRecord->id,
        ], 202);
    }
}
