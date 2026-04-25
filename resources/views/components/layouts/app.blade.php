<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-dvh bg-zinc-950 antialiased">
        <div
            class="relative isolate min-h-dvh overflow-x-clip bg-zinc-900 md:mx-auto md:max-w-screen-md md:overflow-x-visible md:border-t md:border-zinc-800/70"
        >
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
                class="pointer-events-none absolute top-0 right-full z-[11] hidden h-[600px] w-[calc((100vw-768px)/2)] backdrop-blur-sm md:block"
            ></div>
            <div
                class="pointer-events-none absolute top-0 left-full z-[11] hidden h-[600px] w-[calc((100vw-768px)/2)] backdrop-blur-sm md:block"
            ></div>

            <div class="pointer-events-none absolute inset-y-0 left-0 z-[15] hidden w-px bg-zinc-800/70 md:block"></div>
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

                    <div
                        class="**:data-[flux-button]:drop-shadow-glow **:data-[flux-button]:transition-[filter] **:data-[flux-button]:duration-300 **:data-[flux-button]:ease-out"
                        x-bind:class="{ '**:data-[flux-button]:drop-shadow-none': scrolled }"
                    >
                        <flux:dropdown align="end">
                            <flux:button variant="ghost" class="rounded-full !p-0" square>
                                <flux:avatar
                                    size="sm"
                                    circle
                                    :src="auth()->user()->plex_thumb"
                                    :name="auth()->user()->name"
                                />
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
                </flux:header>

                <flux:main>
                    <div class="px-4 pb-6 sm:px-6">
                        {{ $slot }}
                    </div>
                </flux:main>

                <footer
                    class="flex items-center justify-center gap-2 border-t border-zinc-800/70 bg-black p-1 text-xs text-zinc-400 [grid-area:footer] md:border-x"
                >
                    <span class="font-[Josefin_Slab] font-semibold">Made with 🤠 in Wyoming</span>
                    {{--
                        <span>·</span>
                        <flux:modal.trigger name="credits">
                        <button type="button" class="cursor-pointer transition-colors hover:text-white">Credits</button>
                        </flux:modal.trigger>
                        <span>·</span>
                        <span>Changelog</span>
                    --}}
                </footer>

                {{--
                    <flux:modal name="credits" size="sm">
                    <div class="space-y-4">
                    <flux:heading size="lg">Credits</flux:heading>
                    <flux:text>Foobar</flux:text>
                    </div>
                    </flux:modal>
                --}}
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
