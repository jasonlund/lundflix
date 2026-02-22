@php
    $defaultBackground = Vite::image('default-background.jpg');
    $backgroundImage ??= $defaultBackground;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen overflow-x-hidden bg-zinc-950">
        <div class="relative min-h-screen bg-zinc-900 md:mx-auto md:max-w-screen-md md:border-y md:border-zinc-800/70">
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
                <div class="absolute inset-x-0 bottom-0 h-2/3 bg-gradient-to-b from-transparent to-black/70"></div>
                <div class="absolute inset-0 bg-gradient-to-t from-zinc-950/90 via-zinc-950/60 to-zinc-950/10"></div>
                <div
                    class="absolute inset-0 bg-gradient-to-r from-zinc-950/25 via-transparent via-20% to-transparent"
                ></div>
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

            <div class="relative z-20">
                <flux:toast.group>
                    <flux:toast />
                </flux:toast.group>

                <flux:header
                    x-data="{ scrolled: false }"
                    x-init="scrolled = window.scrollY > $el.offsetHeight / 2"
                    x-on:scroll.window.passive="scrolled = window.scrollY > $el.offsetHeight / 2"
                    x-bind:class="
                        scrolled
                            ? 'bg-zinc-900/75 backdrop-blur-sm border-zinc-700'
                            : 'border-transparent'
                    "
                    class="sticky top-0 z-20 -mt-px border-b border-transparent transition-[background-color,backdrop-filter,border-color] duration-300 ease-out"
                >
                    <flux:brand
                        href="{{ route('home') }}"
                        wire:navigate
                        :logo="Vite::image('logo.png')"
                        class="me-4"
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
                            <livewire:cart.dropdown />
                        @endpersist
                    </div>

                    <flux:spacer />

                    <div
                        class="**:data-[flux-button]:drop-shadow-glow **:data-[flux-button]:transition-[filter] **:data-[flux-button]:duration-300 **:data-[flux-button]:ease-out"
                        x-bind:class="{ '**:data-[flux-button]:drop-shadow-none': scrolled }"
                    >
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <flux:button type="submit" variant="ghost" icon="log-out">
                                <span class="sr-only sm:not-sr-only">Logout</span>
                            </flux:button>
                        </form>
                    </div>
                </flux:header>

                <flux:main :padding="false">
                    <div class="px-4 pb-6 sm:px-6">
                        {{ $slot }}
                    </div>
                </flux:main>
            </div>
        </div>

        <livewire:media-search />

        @fluxScripts
    </body>
</html>
