<x-layouts.guest>
    <div class="flex min-h-screen items-center justify-center">
        <div class="w-full max-w-md">
            <flux:card class="relative overflow-hidden">
                <div class="pointer-events-none absolute inset-0 overflow-hidden">
                    <img src="{{ Vite::image('default-background.svg') }}" class="h-full w-full object-cover" />
                    <div class="absolute inset-x-0 bottom-0 h-2/3 bg-gradient-to-b from-transparent to-black/70"></div>
                    <div
                        class="absolute inset-0 bg-gradient-to-t from-zinc-950/90 via-zinc-950/60 to-zinc-950/10"
                    ></div>
                    <div class="absolute inset-0 bg-black/25"></div>
                    <x-crt-effects />
                </div>

                <div class="relative">
                    <x-lundbergh-bubble>
                        {{ __('lundbergh.form.password_reset_verified') }}
                    </x-lundbergh-bubble>

                    @if ($errors->any())
                        <div class="mt-4">
                            @foreach ($errors->all() as $error)
                                <flux:text class="text-red-400">{{ $error }}</flux:text>
                            @endforeach
                        </div>
                    @endif

                    <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-6">
                        @csrf

                        <input type="hidden" name="token" value="{{ $request->route('token') }}" />
                        <input type="hidden" name="email" value="{{ $request->email }}" />

                        <flux:input label="Email" type="email" :value="$request->email" disabled />

                        <flux:field>
                            <flux:label>New Password</flux:label>
                            <flux:input name="password" type="password" required autofocus viewable />
                            <flux:error name="password" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Confirm Password</flux:label>
                            <flux:input name="password_confirmation" type="password" required viewable />
                        </flux:field>

                        <flux:button type="submit" variant="primary" class="w-full">Reset Password</flux:button>
                    </form>
                </div>
            </flux:card>
        </div>
    </div>
</x-layouts.guest>
