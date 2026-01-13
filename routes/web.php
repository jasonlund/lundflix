<?php

use App\Http\Controllers\Auth\PlexCallbackController;
use App\Http\Controllers\Auth\PlexRedirectController;
use App\Livewire\Auth\Register;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('dashboard');
})->middleware('auth')->name('home');

// Plex authentication (guests only)
Route::middleware('guest')->group(function () {
    Route::get('/auth/plex', PlexRedirectController::class)->name('auth.plex');
    Route::get('/auth/plex/callback', PlexCallbackController::class)->name('auth.plex.callback');
    Route::get('/register', Register::class)->name('register');
});
