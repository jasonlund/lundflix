<?php

use App\Services\PlexService;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

// Plex auth test routes
Route::get('/plex/test', function (PlexService $plex) {
    $pin = $plex->createPin();
    session(['plex_pin_id' => $pin['id']]);

    return redirect($plex->getAuthUrl($pin['code'], url('/plex/callback')));
});

Route::get('/plex/callback', function (PlexService $plex) {
    $pinId = session('plex_pin_id');
    $token = $plex->getAuthToken($pinId);
    $user = $plex->getUserInfo($token);
    $hasAccess = $plex->hasServerAccess($token);

    // Check if token is a JWT (3 dot-separated parts)
    $isJwt = $token && substr_count($token, '.') === 2;

    dd([
        'jwt_token' => $token,
        'is_jwt_format' => $isJwt,
        'user' => $user,
        'has_server_access' => $hasAccess,
    ]);
});
