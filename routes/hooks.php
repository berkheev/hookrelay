<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\IngestWebhookController;
use App\Http\Controllers\SinkController;
use Illuminate\Support\Facades\Route;

Route::post('/hooks/{source}', IngestWebhookController::class)
    ->where('source', '[a-z0-9][a-z0-9_-]*')
    ->name('hooks.ingest');

Route::get('/health', HealthController::class)->name('health');

if (!app()->isProduction()) {
    Route::any('/_sink/{mode}', SinkController::class)->name('sink');
}
