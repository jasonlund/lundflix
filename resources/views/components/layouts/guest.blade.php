<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-dvh bg-zinc-950 antialiased">
        {{ $slot }}
        @fluxScripts
    </body>
</html>
