<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class RaceController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => [],
            'meta' => [
                'total' => 0,
                'message' => 'Histórico de corridas — migrations e job pendentes (Fase 1)',
            ],
        ]);
    }
}
