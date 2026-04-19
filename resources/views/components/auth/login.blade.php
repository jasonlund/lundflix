<?php

use App\Services\ThirdParty\PlexService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Layout('components.layouts.guest')] #[Title('Sign In')] class extends Component {
    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required')]
    public string $password = '';

    public bool $remember = false;

    public string $plexError = '';

    public function redirectToPlex(PlexService $plex, string $intent = 'register'): void
    {
        try {
            $pin = $plex->createPin();
            session([
                'plex_pin_id' => $pin['id'],
                'plex_intent' => $intent,
            ]);

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
        <flux:card class="relative overflow-hidden">
            <div class="pointer-events-none absolute inset-0 overflow-hidden">
                <img src="{{ Vite::image('default-background.svg') }}" class="h-full w-full object-cover" />
                <div class="absolute inset-x-0 bottom-0 h-2/3 bg-gradient-to-b from-transparent to-black/70"></div>
                <div class="absolute inset-0 bg-gradient-to-t from-zinc-950/90 via-zinc-950/60 to-zinc-950/10"></div>
                <div class="absolute inset-0 bg-black/25"></div>
                <x-crt-effects />
            </div>

            <div class="relative">
                <img src="{{ Vite::image('logo.png') }}" alt="Lundflix" class="drop-shadow-glow-subtle mx-auto h-12" />

                @error('plex')
                    <x-lundbergh-bubble variant="error" class="mt-4" contentTag="div">
                        {!! nl2br(e($message)) !!}
                    </x-lundbergh-bubble>
                @enderror

                <form wire:submit="login" class="mt-6 space-y-6">
                    <flux:field>
                        <flux:label>Email</flux:label>
                        <flux:input wire:model.blur="email" type="email" required autofocus />
                        <flux:error name="email" />
                        @unless ($errors->has('password') || $errors->has('plex'))
                            <x-lundbergh-bubble>
                                {{ __('lundbergh.form.email_description') }}
                            </x-lundbergh-bubble>
                        @endunless
                    </flux:field>

                    <flux:field>
                        <flux:label>Password</flux:label>
                        <flux:input wire:model.blur="password" type="password" required viewable />
                        @error('password')
                            <x-lundbergh-bubble variant="error" contentTag="div">
                                {!! nl2br(e($message)) !!}
                            </x-lundbergh-bubble>
                        @enderror
                    </flux:field>

                    <flux:checkbox wire:model="remember" label="Remember me" />

                    <flux:button type="submit" variant="primary" class="w-full">Sign In</flux:button>
                </form>

                <flux:separator class="my-6" />

                <div class="flex flex-col items-center gap-3">
                    <flux:modal.trigger name="plex-register">
                        <flux:button variant="filled" class="w-full items-center justify-center gap-1 backdrop-blur-sm">
                            Register with
                            <x-plex-logo class="h-4" />
                        </flux:button>
                    </flux:modal.trigger>

                    <flux:modal.trigger name="plex-password-reset">
                        <button type="button" class="text-sm text-zinc-400 underline hover:text-white">
                            Forgot Password?
                        </button>
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
                            <flux:button wire:click="redirectToPlex" variant="primary">
                                <flux:icon.loading wire:loading wire:target="redirectToPlex()" class="size-4" />
                                <span
                                    wire:loading.remove
                                    wire:target="redirectToPlex()"
                                    class="inline-flex items-center gap-1"
                                >
                                    Continue to
                                    <x-plex-logo class="h-4" />
                                </span>
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

                        @if ($plexError)
                            <flux:text class="text-red-400">
                                {{ $plexError }}
                            </flux:text>
                        @endif

                        <div class="flex">
                            <flux:spacer />
                            <flux:button wire:click="redirectToPlex('password_reset')" variant="primary">
                                <flux:icon.loading
                                    wire:loading
                                    wire:target="redirectToPlex('password_reset')"
                                    class="size-4"
                                />
                                <span
                                    wire:loading.remove
                                    wire:target="redirectToPlex('password_reset')"
                                    class="inline-flex items-center gap-1"
                                >
                                    Continue to
                                    <x-plex-logo class="h-4" />
                                </span>
                            </flux:button>
                        </div>
                    </div>
                </flux:modal>
            </div>
        </flux:card>
    </div>
</div>
