<x-layouts.app>
    <div class="flex min-h-screen items-center justify-center">
        <div class="w-full max-w-md">
            <flux:card>
                <img src="/images/logo.png" alt="Lundflix" class="h-12 mx-auto" />

                <flux:error name="plex" class="mt-4" />

                <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-6">
                    @csrf

                    <flux:input name="email" label="Email" type="email" required autofocus description:trailing="The address associated with your plex account."
                    />

                    <flux:input name="password" label="Password" type="password" required description:trailing="Not necessarily the password to your plex account." />

                    <flux:button type="submit" variant="primary" class="w-full">Sign In</flux:button>
                </form>

                <flux:separator class="my-6" />

                <div class="flex justify-center">
                    <flux:modal.trigger name="plex-register">
                        <button type="button" class="inline-flex items-center gap-1 border-b border-current pb-px transition-colors hover:text-zinc-400 dark:hover:text-zinc-300">
                            Register with
                            <x-plex-logo class="h-4" />
                        </button>
                    </flux:modal.trigger>
                </div>

                <flux:modal name="plex-register" class="md:w-full md:max-w-lg">
                    <div class="space-y-6">
                        <div>
                            <flux:heading size="lg">You are leaving lundflix</flux:heading>
                            <flux:text class="mt-2">You'll be redirected to plex.tv to authenticate your account and verify your access for registration.</flux:text>
                        </div>
                        <div class="flex">
                            <flux:spacer />
                            <flux:button as="a" href="{{ route('register') }}" variant="primary" class="inline-flex items-center gap-1">
                                Continue to
                                <x-plex-logo class="h-4" />
                            </flux:button>
                        </div>
                    </div>
                </flux:modal>
            </flux:card>
        </div>
    </div>
</x-layouts.app>
