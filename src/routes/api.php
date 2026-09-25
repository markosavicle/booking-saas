<?php

use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\TenantController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Public: tenant landing data and availability, addressed by slug.
Route::scopeBindings()->prefix('tenants/{tenant:slug}')->group(function () {
    Route::get('/', [TenantController::class, 'show']);
    Route::get('/services/{service}/availability', [AvailabilityController::class, 'show']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::post('/bookings/{appointment}/cancel', [BookingController::class, 'cancel']);
});
