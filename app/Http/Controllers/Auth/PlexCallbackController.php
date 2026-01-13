<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PlexService;
use Illuminate\Http\RedirectResponse;

class PlexCallbackController extends Controller
{
    public function __invoke(PlexService $plex): RedirectResponse
    {
        $pinId = session()->pull('plex_pin_id');

        if (! $pinId) {
            return redirect()->route('login')
                ->withErrors(['plex' => __('lundbergh.plex.auth_failed')]);
        }

        $token = $plex->getTokenFromPin($pinId);

        if (! $token) {
            return redirect()->route('login')
                ->withErrors(['plex' => __('lundbergh.plex.auth_failed')]);
        }

        if (! $plex->hasServerAccess($token)) {
            return redirect()->route('login')
                ->withErrors(['plex' => __('lundbergh.plex.no_access')]);
        }

        $plexUser = $plex->getUserInfo($token);

        $user = User::findByPlexId($plexUser['id']);

        // Existing user - redirect to login with message
        if ($user) {
            return redirect()->route('login')
                ->withErrors(['plex' => __('lundbergh.plex.already_linked')]);
        }

        // New user - store Plex data and redirect to registration
        session([
            'plex_registration' => [
                'plex_id' => $plexUser['id'],
                'plex_token' => $token,
                'plex_username' => $plexUser['username'],
                'plex_email' => $plexUser['email'],
                'plex_thumb' => $plexUser['thumb'],
            ],
        ]);

        return redirect()->route('register');
    }
}
