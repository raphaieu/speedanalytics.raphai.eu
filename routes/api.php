<?php

use App\Http\Controllers\Api\CollectorIngestController;
use App\Http\Controllers\Api\CollectorStatusController;
use App\Http\Controllers\Api\RaceController;
use Illuminate\Support\Facades\Route;

Route::get('/collector/status', [CollectorStatusController::class, 'show']);
Route::get('/races', [RaceController::class, 'index']);

Route::post('/collector/speedway', [CollectorIngestController::class, 'store']);
