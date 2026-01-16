<?php

use App\Actions\Fortify\CreateNewUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public string $plexUsername = '';

    public string $plexEmail = '';

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate]
    public string $password = '';

    public string $password_confirmation = '';

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

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

        $validated = $this->validate();

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

                <flux:field>
                    <flux:label>Display Name</flux:label>
                    <flux:input wire:model.blur="name" placeholder="Your display name" required />
                    <flux:error name="name" />
                </flux:field>

                <flux:field>
                    <flux:label>Password</flux:label>
                    <flux:input wire:model.blur="password" type="password" required />
                    <flux:error name="password" />
                </flux:field>

                <flux:field>
                    <flux:label>Confirm Password</flux:label>
                    <flux:input wire:model.blur="password_confirmation" type="password" required />
                </flux:field>

                <flux:button type="submit" variant="primary" class="w-full">Create Account</flux:button>
            </form>
        </flux:card>
    </div>
</div>
