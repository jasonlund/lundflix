<?php

use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    #[On('profile-updated')]
    public function refresh(): void
    {
    }
};
?>

<div
    class="**:data-[flux-button]:drop-shadow-glow **:data-[flux-button]:transition-[filter] **:data-[flux-button]:duration-300 **:data-[flux-button]:ease-out"
    x-bind:class="{ '**:data-[flux-button]:drop-shadow-none': scrolled }"
>
    <flux:dropdown align="end">
        <flux:button variant="ghost" class="rounded-full !p-0" square>
            <flux:avatar size="sm" circle :src="auth()->user()->plex_thumb ?? ''" :name="auth()->user()->name" />
        </flux:button>

        <flux:menu>
            <flux:modal.trigger name="profile">
                <flux:menu.item icon="user">Profile</flux:menu.item>
            </flux:modal.trigger>

            @if (auth()->user()->canAccessPanel(filament()->getPanel('admin')))
                <flux:menu.item icon="shield-check" href="/admin">Admin</flux:menu.item>
            @endif

            <flux:separator />

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <flux:menu.item icon="log-out" type="submit">Logout</flux:menu.item>
            </form>
        </flux:menu>
    </flux:dropdown>
</div>
