<?php

use App\Http\Controllers\ArtController;
use App\Http\Controllers\Auth\PlexCallbackController;
use App\Http\Controllers\Auth\PlexRedirectController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('dashboard');
})->middleware('auth')->name('home');

if (app()->environment(['local', 'staging', 'testing'])) {
    Route::livewire('/demo', 'demo')->middleware('auth')->name('demo');
}

Route::livewire('/shows/{show}', 'shows.show')->middleware('auth')->name('shows.show');
Route::livewire('/movies/{movie}', 'movies.show')->middleware('auth')->name('movies.show');
Route::livewire('/cart/checkout', 'cart.checkout')->middleware('auth')->name('cart.checkout');

Route::get('/art/{mediable}/{id}/{type}', ArtController::class)
    ->whereIn('mediable', ['movie', 'show'])
    ->middleware('auth')
    ->name('art');

// Plex authentication (guests only)
Route::middleware('guest')->group(function () {
    Route::get('/auth/plex', PlexRedirectController::class)->name('auth.plex');
    Route::get('/auth/plex/callback', PlexCallbackController::class)->name('auth.plex.callback');

    // Livewire SFC routes
    Route::livewire('/login', 'auth.login')->name('login');
    Route::livewire('/register', 'auth.register')->name('register');
});
