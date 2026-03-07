<?php

use App\Services\ThirdParty\PlexService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Layout('components.layouts.guest')] class extends Component {
    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required')]
    public string $password = '';

    public bool $remember = false;

    public string $plexError = '';

    public function redirectToPlex(PlexService $plex): void
    {
        try {
            $pin = $plex->createPin();
            session(['plex_pin_id' => $pin['id']]);

            $authUrl = $plex->getAuthUrl($pin['code'], route('auth.plex.callback'));
            $this->redirect($authUrl);
        } catch (\Throwable) {
            $this->plexError = __('lundbergh.plex.pin_creation_failed');
        }
    }

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
        $this->redirectIntended(route('home'));
    }
};
?>

<div class="flex min-h-screen items-center justify-center">
    <div class="w-full max-w-md">
        <flux:card>
            <img src="{{ Vite::image('logo.png') }}" alt="Lundflix" class="mx-auto h-12" />

            <flux:error name="plex" class="mt-4" />

            <form wire:submit="login" class="mt-6 space-y-6">
                <flux:field>
                    <flux:label>Email</flux:label>
                    <flux:input wire:model.blur="email" type="email" required autofocus />
                    <flux:error name="email" />
                    @unless ($errors->has('password'))
                        <x-lundbergh-bubble>
                            {{ __('lundbergh.form.email_description') }}
                        </x-lundbergh-bubble>
                    @endunless
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

            <div class="flex flex-col items-center gap-3">
                <flux:modal.trigger name="plex-register">
                    <flux:button variant="subtle" class="inline-flex items-center gap-1 border-b border-current pb-px">
                        Register with
                        <x-plex-logo class="h-4" />
                    </flux:button>
                </flux:modal.trigger>

                <flux:modal.trigger name="plex-password-reset">
                    <flux:button variant="subtle" class="border-b border-current pb-px">Forgot Password?</flux:button>
                </flux:modal.trigger>
            </div>

            <flux:modal name="plex-register">
                <div class="space-y-6">
                    <div>
                        <flux:heading size="lg">You are leaving lundflix</flux:heading>
                        <flux:text class="mt-2">
                            {!! nl2br(e(__('lundbergh.form.plex_redirect'))) !!}
                        </flux:text>
                    </div>

                    @if ($plexError)
                        <flux:text class="text-red-400">
                            {{ $plexError }}
                        </flux:text>
                    @endif

                    <div class="flex">
                        <flux:spacer />
                        <flux:button
                            wire:click="redirectToPlex"
                            variant="primary"
                            class="inline-flex items-center gap-1"
                        >
                            <flux:icon.loading wire:loading wire:target="redirectToPlex" class="size-4" />
                            <span wire:loading.remove wire:target="redirectToPlex">Continue to</span>
                            <x-plex-logo wire:loading.remove wire:target="redirectToPlex" class="h-4" />
                        </flux:button>
                    </div>
                </div>
            </flux:modal>

            <flux:modal name="plex-password-reset">
                <div class="space-y-6">
                    <div>
                        <flux:heading size="lg">Reset your password</flux:heading>
                        <flux:text class="mt-2">
                            {!! nl2br(e(__('lundbergh.form.plex_password_reset'))) !!}
                        </flux:text>
                    </div>

                    <div class="flex">
                        <flux:spacer />
                        <flux:button variant="primary" class="inline-flex items-center gap-1">
                            Continue to
                            <x-plex-logo class="h-4" />
                        </flux:button>
                    </div>
                </div>
            </flux:modal>
        </flux:card>
    </div>
</div>
