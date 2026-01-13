<div class="flex min-h-screen items-center justify-center">
    <div class="w-full max-w-md">
        <flux:card>
            <flux:heading size="lg">Complete Registration</flux:heading>
            <flux:text class="mt-2">Your Plex account has been verified. Complete your registration below.</flux:text>

            <form wire:submit="register" class="mt-6 space-y-6">
                <flux:input label="Plex Username" :value="$plexUsername" disabled />

                <flux:input label="Email" type="email" :value="$plexEmail" disabled />

                <flux:input wire:model="name" label="Display Name" placeholder="Your display name" required />

                <flux:input wire:model="password" label="Password" type="password" required />

                <flux:input wire:model="password_confirmation" label="Confirm Password" type="password" required />

                <flux:button type="submit" variant="primary" class="w-full">Create Account</flux:button>
            </form>
        </flux:card>
    </div>
</div>
