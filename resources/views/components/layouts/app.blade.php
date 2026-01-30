<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-950">
        <div
            class="min-h-screen bg-white md:mx-auto md:max-w-screen-md md:border md:border-zinc-800/70 dark:bg-zinc-900"
        >
            <flux:header
                class="sticky top-0 z-20 border-b border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900"
            >
                <flux:brand href="{{ route('home') }}" wire:navigate :logo="Vite::image('logo.png')" class="me-4" />

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

        <livewire:media-search />

        @fluxScripts
    </body>
</html>
