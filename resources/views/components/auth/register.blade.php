<?php

use App\Actions\Fortify\CreateNewUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Layout('components.layouts.guest')] #[Title('Register')] class extends Component {
    public string $plexUsername = '';

    public string $plexEmail = '';

    #[Validate('required|string|max:255')]
    public string $name = '';

    public string $password = '';

    public string $password_confirmation = '';

    #[Validate('required|timezone:all')]
    public string $timezone = 'America/New_York';

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
            $this->redirect(route('login'));

            return;
        }

        $this->plexUsername = $plexData['plex_username'];
        $this->plexEmail = $plexData['plex_email'];
        $this->name = $plexData['plex_username'];
    }

    /**
     * @return array<string, string>
     */
    public function timezoneOptions(): array
    {
        $timezones = [];

        foreach (\DateTimeZone::listIdentifiers() as $tz) {
            $timezones[$tz] = str_replace(['/', '_'], [' / ', ' '], $tz);
        }

        return $timezones;
    }

    public function register(CreateNewUser $createUser): void
    {
        $plexData = session()->pull('plex_registration');

        if (! $plexData) {
            $this->redirect(route('login'));

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
            'timezone' => $validated['timezone'],
        ]);

        Auth::login($user, remember: true);

        $this->redirect(route('home'));
    }
};
?>

<div class="flex min-h-dvh items-center justify-center">
    <div class="w-full max-w-md">
        <flux:card class="relative overflow-hidden">
            <x-auth-card-background />

            <div class="relative">
                <flux:heading size="lg">Complete Registration</flux:heading>
                <flux:text class="mt-2">
                    Your Plex account has been verified. Complete your registration below.
                </flux:text>

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
                        <flux:input wire:model.blur="password" type="password" required viewable />
                        <flux:error name="password" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Confirm Password</flux:label>
                        <flux:input wire:model.blur="password_confirmation" type="password" required viewable />
                    </flux:field>

                    <flux:field
                        x-init="
                            try {
                                let tz = Intl.DateTimeFormat().resolvedOptions().timeZone
                                if (tz) {
                                    $wire.timezone = tz
                                }
                            } catch (e) {}
                        "
                    >
                        <flux:label>Timezone</flux:label>
                        <flux:select
                            variant="listbox"
                            searchable
                            wire:model="timezone"
                            placeholder="Select timezone..."
                        >
                            @foreach ($this->timezoneOptions() as $value => $label)
                                <flux:select.option :$value>{{ $label }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="timezone" />
                    </flux:field>

                    <flux:button type="submit" variant="primary" class="w-full">Create Account</flux:button>
                </form>
            </div>
        </flux:card>
    </div>
</div>
