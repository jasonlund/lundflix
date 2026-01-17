<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Layout('components.layouts.app')] class extends Component {
    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required')]
    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $validated = $this->validate();

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
            $this->addError('password', __('auth.failed'));

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
                <flux:field>
                    <flux:label>Email</flux:label>
                    <flux:input wire:model.blur="email" type="email" required autofocus />
                    <flux:error name="email" />
                    <flux:description>{{ __('lundbergh.form.email_description') }}</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>Password</flux:label>
                    <flux:input wire:model.blur="password" type="password" required />
                    <flux:error name="password" />
                </flux:field>

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
                            {!! nl2br(e(__('lundbergh.form.plex_redirect'))) !!}
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
