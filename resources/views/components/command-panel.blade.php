@props([
    'name',
    'panelClass' => '',
    'itemsClass' => '',
    'autoHighlightFirst' => false,
])

<div
    data-command-panel="{{ $name }}"
    class="h-full"
    x-data="{
        usingKeyboard: false,
        autoHighlightFirst: {{ $autoHighlightFirst ? 'true' : 'false' }},
        init() {
            this.$el.addEventListener(
                'keydown',
                () => (this.usingKeyboard = true),
                { capture: true },
            )
            this.$el.addEventListener(
                'pointerdown',
                () => (this.usingKeyboard = false),
                { capture: true },
            )
            this.$el.addEventListener(
                'pointermove',
                () => (this.usingKeyboard = false),
                { capture: true },
            )

            if (this.autoHighlightFirst) {
                this.highlightFirst()

                new MutationObserver(() => this.highlightFirst()).observe(
                    this.$refs.items,
                    { childList: true, subtree: true },
                )
            }
        },
        highlightFirst() {
            const items = this.getItems()
            if (items.length > 0) {
                this.activate(items[0])
            }
        },
        getItems() {
            return [
                ...this.$refs.items.querySelectorAll(
                    ':scope > [data-command-item]:not([disabled])',
                ),
            ].filter((el) => getComputedStyle(el).display !== 'none')
        },
        getActive() {
            return this.$refs.items.querySelector(
                ':scope > [data-command-item][data-active]',
            )
        },
        activate(el) {
            this.getActive()?.removeAttribute('data-active')
            el?.setAttribute('data-active', '')
            if (this.usingKeyboard && el) {
                el.scrollIntoView({ block: 'nearest' })
            }
        },
        deactivate(el) {
            el?.removeAttribute('data-active')
        },
        navigateNext() {
            const items = this.getItems()
            const active = this.getActive()
            if (! active) {
                this.activate(items[0])
                return
            }
            const idx = items.indexOf(active)
            if (idx < items.length - 1) this.activate(items[idx + 1])
        },
        navigatePrev() {
            const items = this.getItems()
            const active = this.getActive()
            if (! active) {
                this.activate(items.at(-1))
                return
            }
            const idx = items.indexOf(active)
            if (idx > 0) this.activate(items[idx - 1])
        },
        selectActive() {
            this.getActive()?.click()
        },
    }"
    x-on:keydown.arrow-down.prevent.stop="navigateNext()"
    x-on:keydown.arrow-up.prevent.stop="navigatePrev()"
    x-on:keydown.enter.prevent.stop="selectActive()"
>
    <div @class(['flex w-full flex-col overflow-hidden', $panelClass])>
        @if (isset($header))
            <div class="flex h-14 items-center gap-2 border-b border-zinc-700/70 bg-zinc-900/75 ps-3 pe-2">
                {{ $header }}
                <flux:button
                    size="sm"
                    variant="subtle"
                    icon="x-mark"
                    class="-mr-1"
                    x-on:click="$dispatch('modal-close', { name: '{{ $name }}' })"
                />
            </div>
        @endif

        <div x-ref="items" @class(['min-h-0 flex-1', $itemsClass])>
            @if (isset($empty))
                <template x-if="! $refs.items?.querySelector('[data-command-item]')">
                    <div>
                        {{ $empty }}
                    </div>
                </template>
            @endif

            {{ $slot }}
        </div>

        @if (isset($footer))
            <div class="border-t border-zinc-700/70">
                {{ $footer }}
            </div>
        @endif
    </div>
</div>
