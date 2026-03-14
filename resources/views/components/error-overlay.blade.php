{{-- Visual structure mirrors errors/error.blade.php (server-side version). Keep in sync. --}}
<div
    x-data="{
        visible: false,
        status: null,
        src: '',
        url: null,
        message: '',
        description: null,
        caption: null,
        traceId: null,
        show(detail) {
            Object.assign(this, detail, { visible: true })
            history.pushState({ errorOverlay: true }, '')
            this.$nextTick(() => this.$refs.video?.play())
        },
        dismiss() {
            this.visible = false
            this.$refs.video?.pause()
        },
        init() {
            window.addEventListener('popstate', () => {
                if (this.visible) this.dismiss()
            })
        },
    }"
    x-on:error-overlay-show.window="show($event.detail)"
    x-show="visible"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/90 backdrop-blur-sm"
>
    <div class="px-6 text-center">
        <div class="relative mx-auto mb-6 max-w-screen-md overflow-hidden rounded-lg">
            <template x-if="url">
                <a x-bind:href="url" data-error-source-link>
                    <video x-ref="video" x-bind:src="src" autoplay loop muted playsinline class="w-full"></video>
                </a>
            </template>
            <template x-if="!url">
                <video x-ref="video" x-bind:src="src" autoplay loop muted playsinline class="w-full"></video>
            </template>
            <x-crt-effects />
            <template x-if="caption">
                <div class="pointer-events-none absolute inset-x-0 bottom-4 flex flex-col items-center gap-1">
                    <template x-for="line in caption" x-bind:key="line">
                        <span
                            x-text="line"
                            class="bg-black/80 px-2 py-0.5 font-mono text-sm tracking-wider text-white uppercase"
                        ></span>
                    </template>
                </div>
            </template>
        </div>
        <p class="text-3xl text-zinc-400">
            <span x-text="status" class="font-mono font-semibold text-white"></span>
            <span class="mx-2 text-zinc-600">&middot;</span>
            <span x-text="message" class="font-serif"></span>
        </p>
        <template x-if="description">
            <p x-text="description" class="mt-2 font-sans text-sm text-zinc-500"></p>
        </template>
        <p
            x-show="status == 500"
            x-text="traceId || '(no trace ID)'"
            class="mt-3 font-mono text-xs text-zinc-600"
        ></p>
    </div>
</div>
