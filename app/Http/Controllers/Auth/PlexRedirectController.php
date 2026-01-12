<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\PlexService;
use Illuminate\Http\RedirectResponse;

class PlexRedirectController extends Controller
{
    public function __invoke(PlexService $plex): RedirectResponse
    {
        $pin = $plex->createPin();

        session(['plex_pin_id' => $pin['id']]);

        return redirect($plex->getAuthUrl($pin['code'], route('auth.plex.callback')));
    }
}
