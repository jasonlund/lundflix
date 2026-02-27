<?php

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

    public function plexConfig(): array
    {
        return [
            'clientIdentifier' => config('services.plex.client_identifier'),
            'productName' => config('services.plex.product_name'),
            'callbackUrl' => route('auth.plex.callback'),
        ];
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

<div class="flex min-h-screen items-center justify-center px-4">
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

            <div class="flex justify-center">
                <flux:modal.trigger name="plex-register">
                    <flux:button variant="subtle" class="inline-flex items-center gap-1 border-b border-current pb-px">
                        Register with
                        <x-plex-logo class="h-4" />
                    </flux:button>
                </flux:modal.trigger>
            </div>

            <flux:modal name="plex-register" class="md:w-full md:max-w-lg">
                <div
                    class="space-y-6"
                    x-data="{
                        loading: false,
                        error: false,
                        config: {{ Js::from($this->plexConfig()) }},
                        async redirectToPlex() {
                            this.loading = true
                            this.error = false

                            try {
                                const response = await fetch(
                                    'https://plex.tv/api/v2/pins?strong=true',
                                    {
                                        method: 'POST',
                                        headers: {
                                            'Accept': 'application/json',
                                            'Content-Type': 'application/x-www-form-urlencoded',
                                            'X-Plex-Client-Identifier':
                                                this.config.clientIdentifier,
                                            'X-Plex-Product': this.config.productName,
                                            'X-Plex-Version': '1.0.0',
                                            'X-Plex-Device-Name': this.config.productName,
                                        },
                                    },
                                )

                                if (! response.ok) throw new Error()

                                const data = await response.json()
                                const forwardUrl = new URL(this.config.callbackUrl)
                                forwardUrl.searchParams.set('pin_id', data.id)

                                const params = new URLSearchParams({
                                    clientID: this.config.clientIdentifier,
                                    code: data.code,
                                    forwardUrl: forwardUrl.toString(),
                                    'context[device][product]': this.config.productName,
                                })

                                window.location.href =
                                    'https://app.plex.tv/auth#?' + params.toString()
                            } catch {
                                this.loading = false
                                this.error = true
                            }
                        },
                    }"
                >
                    <div>
                        <flux:heading size="lg">You are leaving lundflix</flux:heading>
                        <flux:text class="mt-2">
                            {!! nl2br(e(__('lundbergh.form.plex_redirect'))) !!}
                        </flux:text>
                    </div>

                    <flux:text x-show="error" x-cloak class="text-red-400">
                        {{ __('lundbergh.plex.pin_creation_failed') }}
                    </flux:text>

                    <div class="flex">
                        <flux:spacer />
                        <flux:button
                            variant="primary"
                            class="inline-flex items-center gap-1"
                            x-on:click="redirectToPlex()"
                            x-bind:disabled="loading"
                        >
                            <flux:icon.loading x-show="loading" x-cloak class="size-4" />
                            <span x-show="!loading">Continue to</span>
                            <x-plex-logo x-show="!loading" class="h-4" />
                        </flux:button>
                    </div>
                </div>
            </flux:modal>
        </flux:card>
    </div>
</div>
