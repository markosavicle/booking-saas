<?php

use App\Http\Controllers\Api\BookingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/services/{service}/slots', [BookingController::class, 'availableSlots']);
    Route::post('/bookings', [BookingController::class, 'store']);
});
