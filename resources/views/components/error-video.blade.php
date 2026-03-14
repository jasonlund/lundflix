@props([
    'src',
    'url' => null,
    'caption' => null,
])

<div class="relative mx-auto mb-6 max-w-screen-md overflow-hidden rounded-lg">
    @if ($url)
        <a href="{{ $url }}">
            <video src="{{ $src }}" autoplay loop muted playsinline class="w-full"></video>
        </a>
    @else
        <video src="{{ $src }}" autoplay loop muted playsinline class="w-full"></video>
    @endif
    <x-crt-effects />
    @if ($caption)
        <div class="pointer-events-none absolute inset-x-0 bottom-4 flex flex-col items-center gap-1">
            @foreach ($caption as $line)
                <span class="bg-black/80 px-2 py-0.5 font-mono text-sm tracking-wider text-white uppercase">
                    {{ $line }}
                </span>
            @endforeach
        </div>
    @endif
</div>
