<?php

use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\EventController;
use App\Http\Middleware\AdminBasicAuth;
use Illuminate\Support\Facades\Route;

Route::middleware(AdminBasicAuth::class)->name('api.')->group(function () {
    Route::get('/events', [EventController::class, 'index'])->name('events.index');
    Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');
    Route::post('/deliveries/{delivery}/replay', [DeliveryController::class, 'replay'])->name('deliveries.replay');
});
