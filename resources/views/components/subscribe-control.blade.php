@props([
    'title',
    'mode' => null,
])

@php
    $modes = \App\Enums\SubscriptionMode::cases();
    $triggerClasses = 'focus-visible:ring-lundflix flex cursor-pointer items-center justify-center gap-2 rounded-full border-1 px-5 py-3 text-sm font-medium backdrop-blur-sm transition focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-offset-zinc-950 focus-visible:outline-none sm:gap-1.5 sm:px-4 sm:py-2.5 sm:text-xs';
@endphp

@if ($mode === null)
    <div x-data="{ syncing: false }" wire:key="subscribe-control-off">
        <button
            type="button"
            x-on:click="
                syncing = true
                $wire.subscribe().then(() => {
                    syncing = false
                })
            "
            aria-label="Subscribe to {{ $title }}"
            class="{{ $triggerClasses }} border-zinc-600 bg-white/10 text-white hover:bg-white/20"
        >
            <div class="relative flex items-center justify-center">
                <flux:icon.bell x-bind:class="syncing && 'opacity-0'" class="size-5 shrink-0 sm:size-4" />
                <flux:icon.loading x-show="syncing" x-cloak class="absolute size-5 sm:size-4" />
            </div>
            <span x-bind:class="syncing && 'opacity-0'" aria-live="polite" x-bind:aria-busy="syncing">
                Subscribe
            </span>
        </button>
    </div>
@else
    <flux:dropdown align="start" wire:key="subscribe-control-on">
        <button
            type="button"
            aria-label="Manage subscription to {{ $title }}"
            class="{{ $triggerClasses }} group border-lundflix bg-lundflix/20 hover:bg-lundflix/30 text-white"
        >
            <flux:icon.bell :variant="$mode->iconVariant()" class="size-5 shrink-0 sm:size-4" />
            <span>{{ $mode->label() }}</span>
            <flux:icon.chevron-down
                class="size-4 shrink-0 transition-transform group-aria-expanded:rotate-180 sm:size-3.5"
            />
        </button>

        <flux:menu>
            @foreach ($modes as $option)
                <flux:menu.item
                    icon="bell"
                    :icon:variant="$option->iconVariant()"
                    :icon:trailing="$mode === $option ? 'check' : null"
                    wire:click="setMode('{{ $option->value }}')"
                >
                    {{ $option->menuLabel() }}
                </flux:menu.item>
            @endforeach

            <flux:separator />

            <flux:menu.item icon="user-minus" variant="danger" wire:click="unsubscribe">Unsubscribe</flux:menu.item>
        </flux:menu>
    </flux:dropdown>
@endif
