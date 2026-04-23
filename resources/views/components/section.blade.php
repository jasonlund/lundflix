@props([
    'heading' => '',
    'collapsible' => false,
    'expanded' => false,
])

<flux:card class="overflow-hidden p-3">
    @if ($collapsible)
        <div x-data="{ open: {{ $expanded ? 'true' : 'false' }} }">
            <button
                @click="open = !open"
                class="-m-3 flex w-[calc(100%+1.5rem)] cursor-pointer items-center justify-between p-3"
            >
                <div class="flex items-center gap-2">
                    @if ($heading)
                        <flux:heading size="sm">{{ $heading }}</flux:heading>
                    @endif

                    {{ $badge ?? '' }}
                </div>

                <div class="flex items-center gap-3">
                    {{ $action ?? '' }}

                    <flux:icon.chevron-down
                        class="size-4 text-zinc-400 transition-transform duration-200"
                        x-bind:class="open && 'rotate-180'"
                    />
                </div>
            </button>

            <div x-show="open" x-collapse>
                {{ $slot }}
            </div>
        </div>
    @else
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                @if ($heading)
                    <flux:heading size="sm">{{ $heading }}</flux:heading>
                @endif

                {{ $badge ?? '' }}
            </div>

            {{ $action ?? '' }}
        </div>

        {{ $slot }}
    @endif
</flux:card>
