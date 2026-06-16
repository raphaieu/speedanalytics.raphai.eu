<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;

class CollectorStatusController extends Controller
{
    public function show(): JsonResponse
    {
        $path = config('speedway.collector_status_path');

        if (! File::isFile($path)) {
            return response()->json([
                'status' => 'unknown',
                'message' => 'Arquivo de status do collector não encontrado',
                'path' => $path,
                'needs_login' => null,
                'last_payload_at' => null,
                'last_external_id' => null,
            ]);
        }

        $data = json_decode(File::get($path), true);

        if (! is_array($data)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Status do collector inválido',
            ], 500);
        }

        return response()->json($data);
    }
}
