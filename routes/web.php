<?php

use App\Http\Controllers\Auth\PlexCallbackController;
use App\Http\Controllers\Auth\PlexLegacyCallbackController;
use App\Http\Controllers\Auth\PlexLegacyRedirectController;
use App\Http\Controllers\Auth\PlexRedirectController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

// Plex authentication (JWT)
Route::get('/auth/plex', PlexRedirectController::class)->name('auth.plex');
Route::get('/auth/plex/callback', PlexCallbackController::class)->name('auth.plex.callback');

// Plex authentication (Legacy)
Route::get('/auth/plex/legacy', PlexLegacyRedirectController::class)->name('auth.plex.legacy');
Route::get('/auth/plex/legacy/callback', PlexLegacyCallbackController::class)->name('auth.plex.legacy.callback');
