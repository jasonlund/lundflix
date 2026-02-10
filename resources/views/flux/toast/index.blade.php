@blaze

@props([
    'position' => 'bottom end',
])

<ui-toast
    x-data
    x-on:toast-show.document="! $el.closest('ui-toast-group') && $el.showToast($event.detail)"
    popover="manual"
    position="{{ $position }}"
    wire:ignore
>
    <template>
        <div
            {{ $attributes->only(['class'])->class('in-[ui-toast-group]:max-w-auto max-w-sm in-[ui-toast-group]:w-xs sm:in-[ui-toast-group]:w-sm') }}
            data-variant=""
            data-flux-toast-dialog
        >
            <div class="p-2">
                <x-lundbergh-bubble :with-margin="false">
                    <span class="flex flex-col gap-1">
                        <span class="text-sm font-semibold text-zinc-100 empty:hidden">
                            <slot name="heading"></slot>
                        </span>
                        <span class="text-sm text-zinc-300">
                            <slot name="text"></slot>
                        </span>
                    </span>
                </x-lundbergh-bubble>
            </div>
        </div>
    </template>
</ui-toast>
