<?php

use App\Http\Controllers\BookingPageController;
use App\Http\Controllers\CancelBookingPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/book/{tenant:slug?}', BookingPageController::class)->name('booking');

// Public cancel link: the unguessable token is the credential.
Route::middleware('throttle:booking-cancel')->group(function () {
    Route::get('/cancel/{appointment:cancel_token}', [CancelBookingPageController::class, 'show'])->name('booking.cancel');
    Route::post('/cancel/{appointment:cancel_token}', [CancelBookingPageController::class, 'destroy']);
});
