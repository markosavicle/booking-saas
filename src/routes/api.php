<?php

use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\BookingRequestController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', fn (Request $request) => new UserResource($request->user()))->middleware('auth:sanctum');

Route::get('/tenants', [TenantController::class, 'index']);

// Public: tenant landing data and availability, addressed by slug.
Route::scopeBindings()->prefix('tenants/{tenant:slug}')->group(function () {
    Route::get('/', [TenantController::class, 'show']);
    Route::get('/services/{service}/availability', [AvailabilityController::class, 'show']);
});

// Guest booking: request an SMS code, then confirm it to create the appointment.
Route::post('/booking-requests', [BookingRequestController::class, 'store'])->middleware('throttle:booking-otp');
Route::post('/booking-requests/{bookingRequest}/confirm', [BookingRequestController::class, 'confirm'])
    ->whereUuid('bookingRequest')
    ->middleware('throttle:booking-confirm');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store'])->middleware('throttle:bookings');
    Route::post('/bookings/{appointment}/cancel', [BookingController::class, 'cancel']);
});
