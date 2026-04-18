<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ThirdParty\PlexService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use InvalidArgumentException;

class PlexCallbackController extends Controller
{
    public function __invoke(Request $request, PlexService $plex): RedirectResponse
    {
        $pinId = session()->pull('plex_pin_id');

        if (! $pinId) {
            report(new InvalidArgumentException('Plex auth failed: missing plex_pin_id from session'));

            return redirect()->route('login')
                ->withErrors(['plex' => __('lundbergh.plex.auth_failed')]);
        }

        $token = $plex->getTokenFromPin($pinId);

        if (! $token) {
            report(new InvalidArgumentException(sprintf(
                'Plex auth failed: could not retrieve token for pin_id=%s',
                $pinId
            )));

            return redirect()->route('login')
                ->withErrors(['plex' => __('lundbergh.plex.auth_failed')]);
        }

        $plexUser = $plex->getUserInfo($token);

        if (! $plex->hasServerAccess($token)) {
            report(new InvalidArgumentException(sprintf(
                'Plex auth failed: user has no server access (plex_id=%s, plex_username=%s, plex_email=%s)',
                $plexUser['id'],
                $plexUser['username'],
                $plexUser['email']
            )));

            return redirect()->route('login')
                ->withErrors(['plex' => __('lundbergh.plex.no_access')]);
        }

        $intent = session()->pull('plex_intent', 'register');
        $user = User::findByPlexId((string) $plexUser['id']);

        if ($intent === 'password_reset') {
            return $this->handlePasswordReset($user, $token);
        }

        // Existing user - redirect to login with message
        if ($user) {
            report(new InvalidArgumentException(sprintf(
                'Plex auth failed: user already linked (plex_id=%s, plex_username=%s, plex_email=%s, existing_user_id=%s)',
                $plexUser['id'],
                $plexUser['username'],
                $plexUser['email'],
                $user->id
            )));

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

    private function handlePasswordReset(?User $user, string $plexToken): RedirectResponse
    {
        if (! $user) {
            return redirect()->route('login')
                ->withErrors(['plex' => __('lundbergh.plex.no_account')]);
        }

        $user->update(['plex_token' => $plexToken]);

        /** @var \Illuminate\Auth\Passwords\PasswordBroker $broker */
        $broker = Password::broker();
        $resetToken = $broker->createToken($user);

        return redirect()->route('password.reset', [
            'token' => $resetToken,
            'email' => $user->email,
        ]);
    }
}
