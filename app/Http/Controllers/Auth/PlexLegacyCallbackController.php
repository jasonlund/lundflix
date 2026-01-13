<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\PlexService;
use Illuminate\Http\RedirectResponse;

class PlexLegacyCallbackController extends Controller
{
    public function __invoke(PlexService $plex): RedirectResponse
    {
        $pinId = session()->pull('plex_legacy_pin_id');

        if (! $pinId) {
            return redirect()->route('home')
                ->with('error', 'Invalid authentication session.');
        }

        $token = $plex->getLegacyTokenFromPin($pinId);

        if (! $token) {
            return redirect()->route('home')
                ->with('error', 'Authentication failed. Please try again.');
        }

        $userInfo = $plex->getUserInfo($token);
        $resources = $plex->getUserResources($token);

        dd([
            'token' => $token,
            'user_info' => $userInfo,
            'resources' => $resources->toArray(),
        ]);
    }
}
