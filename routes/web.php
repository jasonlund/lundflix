<?php

use App\Http\Controllers\Auth\PlexCallbackController;
use App\Http\Controllers\Auth\PlexRedirectController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('dashboard');
})->middleware('auth')->name('home');

Route::livewire('/shows/{show}', 'shows.show')->middleware('auth')->name('shows.show');

// Plex authentication (guests only)
Route::middleware('guest')->group(function () {
    Route::get('/auth/plex', PlexRedirectController::class)->name('auth.plex');
    Route::get('/auth/plex/callback', PlexCallbackController::class)->name('auth.plex.callback');

    // Livewire SFC routes
    Route::livewire('/login', 'auth.login')->name('login');
    Route::livewire('/register', 'auth.register')->name('register');
});
