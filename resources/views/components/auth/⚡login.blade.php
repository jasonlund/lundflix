<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $validated = $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $throttleKey = Str::transliterate(Str::lower($this->email) . '|' . request()->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $this->addError(
                'email',
                __('auth.throttle', [
                    'seconds' => RateLimiter::availableIn($throttleKey),
                ]),
            );

            return;
        }

        if (! Auth::attempt($validated, $this->remember)) {
            RateLimiter::hit($throttleKey);
            $this->addError('email', __('auth.failed'));

            return;
        }

        RateLimiter::clear($throttleKey);
        session()->regenerate();
        $this->redirect('/');
    }
};
?>

<div class="flex min-h-screen items-center justify-center">
    <div class="w-full max-w-md">
        <flux:card>
            <img src="/images/logo.png" alt="Lundflix" class="mx-auto h-12" />

            <flux:error name="plex" class="mt-4" />

            <form wire:submit="login" class="mt-6 space-y-6">
                <flux:input
                    wire:model="email"
                    label="Email"
                    type="email"
                    required
                    autofocus
                    description:trailing="The address associated with your plex account."
                />

                <flux:input
                    wire:model="password"
                    label="Password"
                    type="password"
                    required
                    description:trailing="Not necessarily the password to your plex account."
                />

                <flux:checkbox wire:model="remember" label="Remember me" />

                <flux:button type="submit" variant="primary" class="w-full">Sign In</flux:button>
            </form>

            <flux:separator class="my-6" />

            <div class="flex justify-center">
                <flux:modal.trigger name="plex-register">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 border-b border-current pb-px transition-colors hover:text-zinc-400 dark:hover:text-zinc-300"
                    >
                        Register with
                        <x-plex-logo class="h-4" />
                    </button>
                </flux:modal.trigger>
            </div>

            <flux:modal name="plex-register" class="md:w-full md:max-w-lg">
                <div class="space-y-6">
                    <div>
                        <flux:heading size="lg">You are leaving lundflix</flux:heading>
                        <flux:text class="mt-2">
                            You'll be redirected to plex.tv to authenticate your account and verify your access for
                            registration.
                        </flux:text>
                    </div>
                    <div class="flex">
                        <flux:spacer />
                        <flux:button
                            as="a"
                            href="{{ route('auth.plex') }}"
                            variant="primary"
                            class="inline-flex items-center gap-1"
                        >
                            Continue to
                            <x-plex-logo class="h-4" />
                        </flux:button>
                    </div>
                </div>
            </flux:modal>
        </flux:card>
    </div>
</div>
