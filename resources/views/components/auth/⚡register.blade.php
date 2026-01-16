<?php

use App\Actions\Fortify\CreateNewUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('components.layouts.app')] class extends Component {
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

    public function register(CreateNewUser $createUser): void
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

        $user = $createUser->create([
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
};
?>

<div class="flex min-h-screen items-center justify-center">
    <div class="w-full max-w-md">
        <flux:card>
            <flux:heading size="lg">Complete Registration</flux:heading>
            <flux:text class="mt-2">Your Plex account has been verified. Complete your registration below.</flux:text>

            <form wire:submit="register" class="mt-6 space-y-6">
                <flux:input label="Plex Username" :value="$plexUsername" disabled />

                <flux:input label="Email" type="email" :value="$plexEmail" disabled />

                <flux:input wire:model="name" label="Display Name" placeholder="Your display name" required />

                <flux:input wire:model="password" label="Password" type="password" required />

                <flux:input wire:model="password_confirmation" label="Confirm Password" type="password" required />

                <flux:button type="submit" variant="primary" class="w-full">Create Account</flux:button>
            </form>
        </flux:card>
    </div>
</div>
