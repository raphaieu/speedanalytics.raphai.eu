<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CollectorIngestController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $token = $request->header('x-speedway-collector-token');
        $expected = config('speedway.collector_token');

        if (! $expected || ! hash_equals($expected, (string) $token)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Fase 1: enfileirar ProcessSpeedwayPayloadJob
        return response()->json([
            'message' => 'Payload recebido (processamento pendente)',
            'accepted' => true,
        ], 202);
    }
}
