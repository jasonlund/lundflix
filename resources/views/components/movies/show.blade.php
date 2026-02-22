<?php

use App\Models\Movie;
use App\Services\CartService;
use App\Support\Formatters;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public Movie $movie;

    public bool $inCart = false;

    public function mount(Movie $movie, CartService $cart): void
    {
        $this->movie = $movie;
        $this->inCart = $cart->has($this->movie->id);
    }

    #[Computed]
    public function isCartDisabled(): bool
    {
        $status = $this->movie->status;

        if ($status === null) {
            return false;
        }

        return ! $status->isCartable();
    }

    public function toggleCart(CartService $cart): void
    {
        if ($this->isCartDisabled) {
            return;
        }

        $this->inCart = $cart->toggleMovie($this->movie->id);
        $this->dispatch('cart-updated');
    }

    public function imdbUrl(): string
    {
        return "https://www.imdb.com/title/{$this->movie->imdb_id}/";
    }

    #[Computed]
    public function releaseDate(): ?string
    {
        if ($this->movie->release_date) {
            return $this->movie->release_date->format('m/d/y');
        }

        if ($this->movie->year) {
            return (string) $this->movie->year;
        }

        return null;
    }

    #[Computed]
    public function backgroundUrl(): ?string
    {
        return $this->movie->artUrl('background');
    }

    public function contentRating(): ?string
    {
        return $this->movie->contentRating();
    }

    #[Computed]
    public function formattedRuntime(): ?string
    {
        return Formatters::runtime($this->movie->runtime);
    }

    public function render(): mixed
    {
        return $this->view()->layout('components.layouts.app', [
            'backgroundImage' => $this->backgroundUrl(),
        ]);
    }
};
?>

<div class="flex flex-col">
    <div class="relative overflow-hidden">
        @if ($movie->imdb_id)
            <div class="absolute top-4 right-4 z-10">
                <flux:tooltip content="View on IMDb">
                    <a
                        href="{{ $this->imdbUrl() }}"
                        target="_blank"
                        class="flex items-center justify-center rounded-lg border-1 border-zinc-600 bg-white/10 p-2 text-white backdrop-blur-sm transition hover:bg-white/20"
                    >
                        <flux:icon.imdb class="size-8" />
                    </a>
                </flux:tooltip>
            </div>
        @endif

        <div class="relative flex flex-col gap-3 px-4 py-5 text-white sm:px-6 sm:py-6">
            <div class="max-w-4xl">
                <x-artwork
                    :model="$movie"
                    type="logo"
                    :alt="$movie->title . ' logo'"
                    class="h-24 drop-shadow sm:h-28 md:h-40"
                >
                    <flux:heading size="xl">{{ $movie->title }}</flux:heading>
                </x-artwork>
            </div>

            <div class="truncate">
                <flux:heading size="xl" class="inline">{{ $movie->title }}</flux:heading>
                @if ($movie->original_title && $movie->original_title !== $movie->title)
                    <span class="ml-3 text-base">{{ $movie->original_title }}</span>
                @endif
            </div>

            <div class="truncate text-zinc-200">
                @if ($this->releaseDate())
                    <span>{{ $this->releaseDate() }}</span>
                @endif

                @if ($movie->status)
                    @if ($this->releaseDate())
                        <span class="text-zinc-500">&nbsp;&middot;&nbsp;</span>
                    @endif

                    <span class="{{ $movie->status->iconColorClass() }} inline-flex items-center gap-1 align-middle">
                        <x-dynamic-component :component="'flux::icon.' . $movie->status->icon()" variant="mini" />
                        {{ $movie->status->getLabel() }}
                    </span>
                @endif
            </div>

            @if ($movie->genres && count($movie->genres))
                <div class="flex gap-4 truncate text-zinc-200">
                    @foreach ($movie->genres as $genre)
                        <span class="inline-flex items-center gap-1 align-middle">
                            <x-dynamic-component
                                :component="'flux::icon.' . \App\Enums\Genre::iconFor($genre)"
                                variant="mini"
                            />
                            {{ \App\Enums\Genre::labelFor($genre) }}
                        </span>
                    @endforeach
                </div>
            @endif

            <div class="truncate text-sm text-zinc-200">
                @if ($movie->original_language)
                    <span>{{ $movie->original_language->getLabel() }}</span>
                @endif

                @if ($this->formattedRuntime())
                    @if ($movie->original_language)
                        <span class="text-zinc-500">&nbsp;&middot;&nbsp;</span>
                    @endif

                    <span>{{ $this->formattedRuntime() }}</span>
                @endif

                @if ($this->contentRating())
                    @if ($movie->original_language || $this->formattedRuntime())
                        <span class="text-zinc-500">&nbsp;&middot;&nbsp;</span>
                    @endif

                    <span>{{ $this->contentRating() }}</span>
                @endif
            </div>
        </div>

        <div
            x-data="{ inCart: {{ Js::from($inCart) }}, syncing: false }"
            @cart-syncing.window="syncing = true"
            @cart-updated.window="syncing = false"
            class="absolute right-4 bottom-4 z-10"
        >
            @if ($this->isCartDisabled)
                <flux:tooltip content="Not yet released">
                    <div
                        class="flex items-center gap-1.5 rounded-lg border-1 border-zinc-600 bg-white/10 px-3 py-2 text-white/50 backdrop-blur-sm"
                    >
                        <div class="relative flex min-w-4 items-center justify-center">
                            <span>-</span>
                        </div>
                        <flux:icon.shopping-cart class="size-4" />
                    </div>
                </flux:tooltip>
            @else
                <flux:tooltip content="Add/Remove from Cart">
                    <button
                        x-on:click="
                            inCart = ! inCart
                            syncing = true
                            window.dispatchEvent(new CustomEvent('cart-syncing'))
                            $wire.toggleCart()
                        "
                        class="flex items-center gap-1.5 rounded-lg border-1 border-zinc-600 bg-white/10 px-3 py-2 text-white backdrop-blur-sm transition hover:bg-white/20"
                    >
                        <div class="relative flex min-w-4 items-center justify-center">
                            <span class="invisible">-</span>
                            <span x-show="inCart" x-cloak :class="syncing && 'opacity-0'" class="absolute">
                                <flux:icon.check class="size-4" />
                            </span>
                            <span x-show="!inCart" :class="syncing && 'opacity-0'" class="absolute">-</span>
                            <flux:icon.loading x-show="syncing" x-cloak class="absolute size-4" />
                        </div>
                        <flux:icon.shopping-cart class="size-4" />
                    </button>
                </flux:tooltip>
            @endif
        </div>
    </div>

    <div class="flex flex-col gap-8 px-4 sm:px-6">
        @if ($movie->imdb_id)
            <livewire:movies.plex-availability :movie="$movie" lazy />
        @endif
    </div>
</div>
