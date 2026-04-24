<x-layouts.guest>
    <div class="flex min-h-dvh items-center justify-center">
        <div class="w-full max-w-md">
            <flux:card class="relative overflow-hidden">
                <x-auth-card-background />

                <div class="relative mx-auto max-w-xs">
                    <x-lundbergh-bubble>
                        {{ __('lundbergh.form.password_reset_verified') }}
                    </x-lundbergh-bubble>

                    <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-6">
                        @csrf

                        <input type="hidden" name="token" value="{{ $request->route('token') }}" />
                        <input type="hidden" name="email" value="{{ $request->email }}" />

                        <flux:input label="Email" type="email" :value="$request->email" disabled />
                        @error('email')
                            <x-lundbergh-bubble variant="error" contentTag="div">
                                {!! nl2br(e($message)) !!}
                            </x-lundbergh-bubble>
                        @enderror

                        <flux:field>
                            <flux:label>New Password</flux:label>
                            <flux:input name="password" type="password" required autofocus viewable />
                            @error('password')
                                <x-lundbergh-bubble variant="error" contentTag="div">
                                    {!! nl2br(e($message)) !!}
                                </x-lundbergh-bubble>
                            @enderror
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
