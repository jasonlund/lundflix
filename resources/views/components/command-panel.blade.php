@props([
    'name',
    'panelClass' => '',
    'itemsClass' => '',
])

<div
    data-command-panel="{{ $name }}"
    class="h-full"
    x-data="{
        usingKeyboard: false,
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
            {{ $header }}
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

            @if (isset($footer))
                {{ $footer }}
            @endif
        </div>
    </div>
</div>
