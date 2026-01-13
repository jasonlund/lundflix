<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

class Register extends Component
{
    public string $plexUsername = '';

    public string $plexEmail = '';

    public string $name = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $plexData = session('plex_registration');

        if (! $plexData) {
            $this->redirect(route('auth.plex'));

            return;
        }

        $this->plexUsername = $plexData['plex_username'];
        $this->plexEmail = $plexData['plex_email'];
        $this->name = $plexData['plex_username'];
    }

    public function register(): void
    {
        $plexData = session()->pull('plex_registration');

        if (! $plexData) {
            $this->redirect(route('auth.plex'));

            return;
        }

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $plexData['plex_email'],
            'password' => $validated['password'],
            'plex_id' => $plexData['plex_id'],
            'plex_token' => $plexData['plex_token'],
            'plex_username' => $plexData['plex_username'],
            'plex_thumb' => $plexData['plex_thumb'],
        ]);

        Auth::login($user, remember: true);

        $this->redirect('/');
    }

    public function render()
    {
        return view('livewire.auth.register')
            ->layout('components.layouts.app');
    }
}
