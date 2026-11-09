<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\LocaleController;
use App\Livewire\Auth\Login;
use App\Livewire\Chat;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Browser (session) routes: Google OAuth + Livewire chat UI
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('chat')
        : redirect()->route('login');
})->name('home');

/*
| Language preference: available to guests and signed-in users so the login
| screen and the chat workspace always offer the same choice.
*/
Route::post('/locale/{locale}', LocaleController::class)
    ->whereIn('locale', ['en', 'ar'])
    ->name('locale.switch');

Route::middleware('guest')->group(function () {
    Route::livewire('/login', Login::class)->name('login');

    Route::get('/auth/google/redirect', [AuthController::class, 'webRedirect'])
        ->middleware('throttle:20,1')
        ->name('auth.google.web.redirect');

    Route::get('/auth/google/callback', [AuthController::class, 'webCallback'])
        ->middleware('throttle:20,1')
        ->name('auth.google.web.callback');
});

Route::middleware('auth')->group(function () {
    Route::livewire('/chat', Chat::class)->name('chat');
    Route::post('/logout', [AuthController::class, 'webLogout'])->name('logout');
});

/*
| WebSocket channel authorization for the browser session. The API keeps its
| bearer-token endpoint at /api/broadcasting/auth for non-browser clients.
*/
Broadcast::routes(['middleware' => ['web', 'auth']]);
