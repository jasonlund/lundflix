<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-900">
        <flux:header sticky container class="border-b border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <flux:brand href="{{ route('home') }}" logo="/images/logo.png" class="me-4" />

            <flux:spacer />

            <flux:modal.trigger name="search" shortcut="cmd.k">
                <flux:button variant="ghost" icon="magnifying-glass" kbd="⌘K">Search</flux:button>
            </flux:modal.trigger>

            <livewire:cart.dropdown />

            <flux:spacer />

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <flux:button type="submit" variant="ghost" icon="arrow-right-start-on-rectangle">Logout</flux:button>
            </form>
        </flux:header>

        <flux:main container>
            {{ $slot }}
        </flux:main>

        <livewire:media-search />

        @fluxScripts
    </body>
</html>
