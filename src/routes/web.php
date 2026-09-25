<?php

use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\BookingPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/book/{tenant:slug?}', BookingPageController::class)->name('booking');

// JSON session endpoints for the booking widget (Sanctum SPA cookie auth).
Route::prefix('auth')->group(function () {
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('/register', [SessionController::class, 'register']);
        Route::post('/login', [SessionController::class, 'login']);
    });
    Route::post('/logout', [SessionController::class, 'logout'])->middleware('auth');
});
