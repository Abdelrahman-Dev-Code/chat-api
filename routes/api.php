<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

/*
| Public: Google OAuth sign-in.
*/
Route::prefix('auth/google')
    ->controller(AuthController::class)
    ->middleware('throttle:20,1')
    ->group(function () {
        Route::get('redirect', 'redirect')->name('auth.google.redirect');
        Route::get('callback', 'callback')->name('auth.google.callback');
    });

/*
| Protected: every chat endpoint requires a Sanctum bearer token.
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
});
