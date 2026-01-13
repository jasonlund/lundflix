<?php

use App\Http\Controllers\Auth\PlexCallbackController;
use App\Http\Controllers\Auth\PlexRedirectController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

// Plex authentication
Route::get('/auth/plex', PlexRedirectController::class)->name('auth.plex');
Route::get('/auth/plex/callback', PlexCallbackController::class)->name('auth.plex.callback');
