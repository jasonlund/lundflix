<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PlexService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class PlexCallbackController extends Controller
{
    public function __invoke(PlexService $plex): RedirectResponse
    {
        $pinId = session()->pull('plex_pin_id');

        if (! $pinId) {
            return redirect()->route('login')
                ->with('error', 'Invalid authentication session.');
        }

        if (! $plex->verifyPinClaimed($pinId)) {
            return redirect()->route('login')
                ->with('error', 'Authentication failed. Please try again.');
        }

        $token = $plex->refreshToken();

        if (! $plex->hasServerAccess($token)) {
            return redirect()->route('login')
                ->with('error', 'You do not have access to this server.');
        }

        $plexUser = $plex->getUserInfo($token);

        $user = User::findByPlexId($plexUser['id']) ?? User::create([
            'plex_id' => $plexUser['id'],
            'plex_token' => $token,
            'plex_token_expires_at' => now()->addDays(PlexService::TOKEN_LIFETIME_DAYS),
            'plex_username' => $plexUser['username'],
            'plex_thumb' => $plexUser['thumb'],
            'name' => $plexUser['username'],
            'email' => $plexUser['email'],
        ]);

        if ($user->wasRecentlyCreated === false) {
            $user->update([
                'plex_token' => $token,
                'plex_token_expires_at' => now()->addDays(PlexService::TOKEN_LIFETIME_DAYS),
                'plex_username' => $plexUser['username'],
                'plex_thumb' => $plexUser['thumb'],
            ]);
        }

        Auth::login($user, remember: true);

        return redirect()->intended('/dashboard');
    }
}
