<?php

use App\Http\Controllers\ArtController;
use App\Http\Controllers\Auth\PlexCallbackController;
use App\Http\Controllers\ErrorPreviewController;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;

Route::get('/', fn (): Factory|View => view('dashboard'))->middleware('auth')->name('home');

if (app()->environment(['local', 'staging', 'testing'])) {
    Route::livewire('/demo', 'demo')->middleware('auth')->name('demo');
}

Route::livewire('/shows/{show}', 'shows.show')->middleware('auth')->name('shows.show');
Route::livewire('/movies/{movie}', 'movies.show')->middleware('auth')->name('movies.show');

Route::get('/art/{mediable}/{id}/{type}', ArtController::class)
    ->whereIn('mediable', ['movie', 'show'])
    ->whereIn('type', ['logo', 'poster', 'background'])
    ->middleware('auth')
    ->name('art');

// Plex authentication (guests only)
Route::middleware('guest')->group(function (): void {
    Route::get('/auth/plex/callback', PlexCallbackController::class)->name('auth.plex.callback');

    // Livewire SFC routes
    Route::livewire('/login', 'auth.login')->name('login');
    Route::livewire('/register', 'auth.register')->name('register');
});

Route::get('/{status}', ErrorPreviewController::class)
    ->where('status', '[0-9]{3}')
    ->name('error-preview');
