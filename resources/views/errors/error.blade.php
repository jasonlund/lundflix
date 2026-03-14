{{-- Visual structure mirrors error-overlay.blade.php (Alpine version). Keep in sync. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="flex min-h-screen items-center justify-center bg-zinc-950">
        <div class="px-6 text-center">
            <x-error-video :src="$src" :url="$url" :caption="$caption" />
            <p class="text-3xl text-zinc-400">
                <span class="font-mono font-semibold text-white">{{ $status }}</span>
                <span class="mx-2 text-zinc-600">&middot;</span>
                <span class="font-serif">{{ $message }}</span>
            </p>
            <p class="mt-2 font-sans text-sm text-zinc-500">
                {{ $description }}
            </p>
            @if ($status === 500)
                <p class="mt-3 font-mono text-xs text-zinc-600">
                    {{ $traceId ?? '(no trace ID)' }}
                </p>
            @endif
        </div>
    </body>
</html>
