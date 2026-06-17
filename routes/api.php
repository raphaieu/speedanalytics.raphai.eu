<?php

use App\Http\Controllers\Api\AnalyticsDistributionController;
use App\Http\Controllers\Api\AnalyticsSummaryController;
use App\Http\Controllers\Api\CollectorIngestController;
use App\Http\Controllers\Api\CollectorStatusController;
use App\Http\Controllers\Api\FavoriteOddsBandsController;
use App\Http\Controllers\Api\RaceController;
use App\Http\Controllers\Api\UnderdogOddsBandsController;
use Illuminate\Support\Facades\Route;

Route::get('/collector/status', [CollectorStatusController::class, 'show']);
Route::get('/races', [RaceController::class, 'index']);
Route::get('/races/{race:external_id}', [RaceController::class, 'show']);
Route::get('/analytics/summary', [AnalyticsSummaryController::class, 'show']);
Route::get('/analytics/distributions', [AnalyticsDistributionController::class, 'show']);
Route::get('/analytics/favorite-odds-bands', [FavoriteOddsBandsController::class, 'index']);
Route::get('/analytics/underdog-odds-bands', [UnderdogOddsBandsController::class, 'index']);

Route::post('/collector/speedway', [CollectorIngestController::class, 'store']);
