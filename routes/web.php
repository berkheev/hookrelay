<?php

use App\Http\Controllers\DashboardController;
use App\Http\Middleware\AdminBasicAuth;
use Illuminate\Support\Facades\Route;

Route::middleware(AdminBasicAuth::class)->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/events/{event}', [DashboardController::class, 'show'])->name('events.show');
    Route::post('/deliveries/{delivery}/replay', [DashboardController::class, 'replay'])->name('deliveries.replay');
});
