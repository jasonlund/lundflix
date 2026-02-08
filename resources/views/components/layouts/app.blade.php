@php
    $backgroundImage ??= Vite::image('default-background.jpg');
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
                <img src="{{ $backgroundImage }}" class="h-full w-full object-cover" />
                <div class="absolute inset-x-0 bottom-0 h-1/2 bg-gradient-to-b from-transparent to-black/60"></div>
                <div class="absolute inset-0 bg-gradient-to-t from-zinc-950/90 via-zinc-950/60 to-zinc-950/10"></div>
                <div class="absolute inset-0 bg-gradient-to-r from-zinc-950/70 via-zinc-950/20 to-transparent"></div>
            </div>

            <div
                class="pointer-events-none absolute top-0 right-full z-[11] hidden h-[600px] w-[calc((100vw-768px)/2)] backdrop-blur-md md:block"
            ></div>
            <div
                class="pointer-events-none absolute top-0 left-full z-[11] hidden h-[600px] w-[calc((100vw-768px)/2)] backdrop-blur-md md:block"
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
                    class="sticky top-0 z-20 -mt-px border-b border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900"
                >
                    <flux:brand
                        href="{{ route('home') }}"
                        wire:navigate
                        :logo="Vite::image('logo.png')"
                        class="me-4"
                    />

                    <flux:spacer />

                    <flux:modal.trigger name="search" shortcut="cmd.k">
                        <flux:button variant="ghost" icon="search" kbd="⌘K">
                            <span class="sr-only sm:not-sr-only">Search</span>
                        </flux:button>
                    </flux:modal.trigger>

                    @persist('cart')
                        <livewire:cart.dropdown />
                    @endpersist

                    <flux:spacer />

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <flux:button type="submit" variant="ghost" icon="log-out">
                            <span class="sr-only sm:not-sr-only">Logout</span>
                        </flux:button>
                    </form>
                </flux:header>

                <flux:main :padding="false">
                    {{ $slot }}
                </flux:main>
            </div>
        </div>

        <livewire:media-search />

        @fluxScripts
    </body>
</html>
