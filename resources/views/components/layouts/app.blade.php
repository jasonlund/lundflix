<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-dvh bg-zinc-950 antialiased">
        <div class="relative min-h-dvh overflow-x-clip">
            <div
                class="pointer-events-none absolute top-0 right-[calc(50%_+_384px)] left-0 z-[11] hidden h-[600px] backdrop-blur-sm md:block"
            ></div>
            <div
                class="pointer-events-none absolute top-0 right-0 left-[calc(50%_+_384px)] z-[11] hidden h-[600px] backdrop-blur-sm md:block"
            ></div>

            <div class="relative isolate min-h-dvh bg-zinc-900 md:mx-auto md:max-w-screen-md">
                <div
                    class="min-w-screen-md pointer-events-none absolute top-0 left-1/2 z-10 -mt-px aspect-video min-h-[10rem] w-full origin-top -translate-x-1/2 scale-135 overflow-hidden rounded-b-xl mask-x-from-70% mask-x-to-95% mask-b-from-65% mask-b-to-97%"
                >
                    <img
                        src="{{ $backgroundImage }}"
                        onerror="
                            this.onerror = null
                            this.src = '{{ $defaultBackground }}'
                        "
                        class="h-full w-full object-cover"
                    />
                    <div class="absolute inset-x-0 bottom-0 h-2/3 bg-linear-to-b from-transparent to-black/70"></div>
                    <div class="absolute inset-0 bg-linear-to-t from-zinc-950/90 via-zinc-950/60 to-zinc-950/10"></div>
                    <div
                        class="absolute inset-0 bg-linear-to-r from-zinc-950/25 via-transparent via-20% to-transparent"
                    ></div>
                    <x-crt-effects />
                </div>

                <div
                    class="pointer-events-none absolute inset-x-0 top-0 z-[15] hidden h-px bg-zinc-800/70 md:block"
                ></div>
                <div
                    class="pointer-events-none absolute inset-y-0 left-0 z-[15] hidden w-px bg-zinc-800/70 md:block"
                ></div>
                <div
                    class="pointer-events-none absolute inset-y-0 right-0 z-[15] hidden w-px bg-zinc-800/70 md:block"
                ></div>

                <div class="relative z-20 min-h-dvh">
                    <flux:header
                        x-data="{ scrolled: false }"
                        x-init="scrolled = window.scrollY > $el.offsetHeight / 2"
                        x-on:scroll.window.passive="scrolled = window.scrollY > $el.offsetHeight / 2"
                        x-bind:class="scrolled ? 'glass-panel border-zinc-700' : 'border-transparent'"
                        class="sticky top-0 z-20 -mt-px border-b border-transparent transition-[background-color,backdrop-filter,border-color] duration-300 ease-out"
                    >
                        <flux:brand
                            href="{{ route('home') }}"
                            wire:navigate
                            :logo="Vite::image('logo.png')"
                            class="drop-shadow-glow-subtle me-4 transition-[filter] duration-300 ease-out"
                            x-bind:class="{ 'drop-shadow-none': scrolled }"
                        />

                        <flux:spacer />

                        <div
                            class="**:data-[flux-button]:drop-shadow-glow flex items-center gap-1 **:data-[flux-button]:cursor-pointer **:data-[flux-button]:bg-white/10 **:data-[flux-button]:backdrop-blur-sm **:data-[flux-button]:transition-[filter,background-color] **:data-[flux-button]:duration-300 **:data-[flux-button]:ease-out **:data-[flux-button]:hover:bg-white/20"
                            x-bind:class="{ '**:data-[flux-button]:drop-shadow-none': scrolled }"
                        >
                            <flux:modal.trigger name="search" shortcut="cmd.k">
                                <flux:button variant="ghost">
                                    <flux:icon name="search" class="text-lundflix size-4" />
                                    <span class="sr-only sm:not-sr-only">Search</span>
                                </flux:button>
                            </flux:modal.trigger>

                            @persist('cart')
                                <livewire:cart />
                            @endpersist
                        </div>

                        <flux:spacer />

                        <livewire:user-menu />
                    </flux:header>

                    <flux:main>
                        <div class="px-4 pb-6 sm:px-6">
                            {{ $slot }}
                        </div>
                    </flux:main>

                    <footer
                        class="flex items-center justify-center gap-2 border-t border-zinc-800/70 bg-black p-1 text-xs text-zinc-400 [grid-area:footer] md:border-x"
                    >
                        <span class="flex items-center gap-1 font-[Josefin_Slab] font-semibold">
                            Made with
                            <x-emoji char="🤠" />
                            in Wyoming
                        </span>
                        <span>·</span>
                        <flux:modal.trigger name="credits">
                            <button
                                type="button"
                                aria-haspopup="dialog"
                                class="cursor-pointer transition-colors hover:text-white"
                            >
                                Credits
                            </button>
                        </flux:modal.trigger>
                    </footer>

                    <flux:modal name="credits" size="md">
                        <div class="space-y-5">
                            <flux:text>{{ __('lundbergh.credits.intro') }}</flux:text>

                            <div
                                class="prose prose-sm prose-invert prose-blockquote:not-italic prose-blockquote:border-l-lundflix prose-blockquote:font-normal prose-p:before:content-none prose-p:after:content-none max-w-none"
                            >
                                <blockquote>
                                    <!-- prettier-ignore -->
                                    <p>This product uses the TMDB API but is not endorsed or certified by <span class="not-prose"><a href="https://www.themoviedb.org" target="_blank" rel="noopener noreferrer" class="transition-opacity hover:opacity-80"><img src="{{ Vite::image('logos/services/tmdb.svg') }}" alt="TMDB" class="inline h-3 align-baseline" /></a></span>.</p>
                                    <!-- prettier-ignore -->
                                    <p>Show data from TVMaze, <a href="https://www.tvmaze.com" target="_blank" rel="noopener noreferrer">https://www.tvmaze.com</a> (<a href="https://creativecommons.org/licenses/by-sa/4.0/" target="_blank" rel="noopener noreferrer">CC BY-SA 4.0</a>).</p>
                                    <!-- prettier-ignore -->
                                    <p>Information courtesy of IMDb. Sourced from the <a href="https://developer.imdb.com/non-commercial-datasets/" target="_blank" rel="noopener noreferrer">IMDb Non-Commercial Datasets</a>.</p>
                                    <!-- prettier-ignore -->
                                    <p>Network logos by Tapio Sinnertwin, <a href="https://github.com/tv-logo/tv-logos" target="_blank" rel="noopener noreferrer">github.com/tv-logo/tv-logos</a>.</p>
                                    <!-- prettier-ignore -->
                                    <p>Emoji artwork &copy; Apple Inc., sourced via <a href="https://emojipedia.org" target="_blank" rel="noopener noreferrer">Emojipedia</a>.</p>
                                </blockquote>
                            </div>
                        </div>
                    </flux:modal>
                </div>
            </div>
        </div>

        <div class="fixed z-50">
            @persist('toast')
                <flux:toast.group>
                    <flux:toast />
                </flux:toast.group>
            @endpersist
        </div>

        <livewire:media-search />
        <livewire:profile-form />

        <x-error-overlay />

        <script>
            window.lundflixErrors = {{ Js::from($errorPages) }}
        </script>

        @fluxScripts
    </body>
</html>
