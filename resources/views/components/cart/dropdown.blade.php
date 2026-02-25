<?php

use App\Actions\Request\CreateRequest;
use App\Actions\Request\CreateRequestItems;
use App\Events\RequestSubmitted;
use App\Services\CartService;
use App\Support\RequestItemFormatter;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public int $itemCount = 0;

    public function mount(CartService $cart): void
    {
        $this->itemCount = $cart->count();
    }

    #[On('cart-updated')]
    public function refreshCount(CartService $cart): void
    {
        $this->itemCount = $cart->count();
        $this->js('$data.syncing = false');
    }

    #[Computed]
    public function groupedCartItems(): array
    {
        return app(CartService::class)->loadGroupedItems();
    }

    public function formatRun(Collection $episodes): string
    {
        return RequestItemFormatter::formatRun($episodes);
    }

    public function formatSeason(int $season): string
    {
        return RequestItemFormatter::formatSeason($season);
    }

    /**
     * Sync episodes for a show - replaces all episodes for that show.
     *
     * @param  array<int, string>  $episodeCodes
     */
    #[On('sync-show-episodes-to-cart')]
    public function syncShowEpisodes(int $showId, array $episodeCodes): void
    {
        app(CartService::class)->syncShowEpisodes($showId, $episodeCodes);
        $this->dispatch('cart-updated');
    }

    /**
     * Toggle a movie in/out of the cart by ID.
     */
    #[On('toggle-movie-in-cart')]
    public function toggleMovieInCart(int $movieId): void
    {
        app(CartService::class)->toggleMovie($movieId);
        $this->dispatch('cart-updated');
    }

    public function submit(CreateRequest $createRequest, CreateRequestItems $createRequestItems): void
    {
        $cart = app(CartService::class);

        if ($cart->isEmpty()) {
            return;
        }

        $request = DB::transaction(function () use ($cart, $createRequest, $createRequestItems) {
            $request = $createRequest->create(Auth::user());

            $items = $cart
                ->loadItems()
                ->map(
                    fn ($item) => [
                        'type' => $item->getMediaType(),
                        'id' => $item->id,
                    ],
                )
                ->all();

            $createRequestItems->create($request, $items);

            $cart->clear();

            return $request;
        });

        RequestSubmitted::dispatch($request);

        $this->dispatch('cart-updated');

        $this->modal('cart')->close();

        Flux::toast(text: __('lundbergh.cart.request_submitted'), variant: 'success');
    }
};
?>

<div x-data="{ syncing: false }" @cart-syncing.window="syncing = true">
    <flux:modal.trigger name="cart">
        <flux:button variant="ghost" ::disabled="syncing">
            <flux:icon.loading x-show="syncing" x-cloak class="size-4" />
            <flux:icon
                name="shopping-cart"
                x-show="!syncing && !$wire.itemCount"
                x-cloak
                class="text-lundflix size-4"
            />
            <span
                x-show="! syncing && $wire.itemCount > 0"
                x-cloak
                class="text-lundflix inline-flex size-4 items-center justify-center text-sm font-bold tabular-nums"
            >
                {{ $itemCount }}
            </span>
            <span class="sr-only sm:not-sr-only">Cart</span>
        </flux:button>
    </flux:modal.trigger>

    @teleport('body')
        <flux:modal
            name="cart"
            variant="bare"
            class="m-0 h-dvh min-h-dvh w-full max-w-none p-0 md:mx-auto md:max-w-screen-md [&::backdrop]:bg-transparent"
        >
            <x-command-panel
                name="cart"
                panelClass="h-full bg-zinc-800/75 backdrop-blur-sm"
                itemsClass="divide-y divide-zinc-700/70 overflow-y-auto"
            >
                <x-slot:header>
                    <div class="flex min-w-0 flex-1 items-center gap-2">
                        <flux:icon name="shopping-cart" variant="mini" class="shrink-0 text-zinc-400" />
                        <span class="text-sm font-medium text-white">Your Cart ({{ $itemCount }})</span>
                    </div>
                </x-slot>

                <x-slot:empty>
                    <div class="px-3 py-4">
                        <x-lundbergh-bubble :with-margin="false">
                            {{ __('lundbergh.empty.cart_dropdown') }}
                        </x-lundbergh-bubble>
                    </div>
                </x-slot>

                @if ($itemCount > 0)
                    <x-slot:footer>
                        <div class="p-3">
                            <button
                                wire:click="submit"
                                wire:loading.attr="disabled"
                                class="flex w-full items-center justify-center gap-2 rounded-lg bg-white/10 px-4 py-2 text-sm font-medium text-white backdrop-blur-sm transition hover:bg-white/20 disabled:opacity-50"
                            >
                                <flux:icon.loading wire:loading wire:target="submit" class="size-4" />
                                <span wire:loading.remove wire:target="submit">Submit Request</span>
                                <span wire:loading wire:target="submit">Submitting…</span>
                            </button>
                        </div>
                    </x-slot>
                @endif

                {{-- Movies --}}
                @foreach ($this->groupedCartItems['movies'] as $movie)
                    <a
                        wire:key="cart-item-movie-{{ $movie->id }}"
                        href="{{ route('movies.show', $movie->id) }}"
                        wire:navigate
                        data-command-item
                        x-on:mouseenter="activate($el)"
                        x-on:mouseleave="deactivate($el)"
                        x-on:click="$dispatch('modal-close', { name: 'cart' })"
                        class="group/item flex h-auto w-full items-center rounded-none p-0 text-white hover:bg-zinc-700/60 focus:outline-hidden data-active:bg-zinc-700/60"
                    >
                        <div class="flex w-full items-center gap-3 px-3 py-1">
                            <flux:icon name="film" variant="mini" class="shrink-0 text-zinc-400" />

                            <div class="flex aspect-[1000/562] w-20 shrink-0 items-center">
                                <x-artwork
                                    :model="$movie"
                                    type="logo"
                                    :alt="$movie->title . ' logo'"
                                    :preview="true"
                                    class="h-full w-full overflow-hidden"
                                />
                            </div>

                            <div class="flex min-w-0 flex-1 flex-col gap-1">
                                <p class="truncate text-base leading-snug text-white">
                                    {{ $movie->title }}
                                </p>
                            </div>

                            <div class="flex shrink-0 items-center pe-1">
                                <flux:icon name="arrow-right" variant="mini" class="size-4 text-zinc-400" />
                            </div>
                        </div>
                    </a>
                @endforeach

                {{-- Shows --}}
                @foreach ($this->groupedCartItems['shows'] as $showGroup)
                    <a
                        wire:key="cart-item-show-{{ $showGroup['show']->id }}"
                        href="{{ route('shows.show', $showGroup['show']->id) }}"
                        wire:navigate
                        data-command-item
                        x-on:mouseenter="activate($el)"
                        x-on:mouseleave="deactivate($el)"
                        x-on:click="$dispatch('modal-close', { name: 'cart' })"
                        class="group/item flex h-auto w-full items-center rounded-none p-0 text-white hover:bg-zinc-700/60 focus:outline-hidden data-active:bg-zinc-700/60"
                    >
                        <div class="flex w-full items-center gap-3 px-3 py-1">
                            <flux:icon name="tv" variant="mini" class="shrink-0 text-zinc-400" />

                            <div class="flex aspect-[1000/562] w-20 shrink-0 items-center">
                                <x-artwork
                                    :model="$showGroup['show']"
                                    type="logo"
                                    :alt="$showGroup['show']->name . ' logo'"
                                    :preview="true"
                                    class="h-full w-full overflow-hidden"
                                />
                            </div>

                            <div class="flex min-w-0 flex-1 flex-col gap-1">
                                <p class="truncate text-base leading-snug text-white">
                                    {{ $showGroup['show']->name }}
                                </p>
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($showGroup['seasons'] as $seasonData)
                                        @if ($seasonData['is_full'])
                                            <flux:badge size="sm" color="zinc">
                                                {{ $this->formatSeason($seasonData['season']) }}
                                            </flux:badge>
                                        @else
                                            @foreach ($seasonData['runs'] as $run)
                                                <flux:badge size="sm" color="zinc">
                                                    {{ $this->formatRun($run) }}
                                                </flux:badge>
                                            @endforeach
                                        @endif
                                    @endforeach
                                </div>
                            </div>

                            <div class="flex shrink-0 items-center pe-1">
                                <flux:icon name="arrow-right" variant="mini" class="size-4 text-zinc-400" />
                            </div>
                        </div>
                    </a>
                @endforeach

                @if ($itemCount > 0)
                    <div class="border-b-0 px-3 py-2">
                        <x-lundbergh-bubble :with-margin="false" contentTag="div">
                            {{ __('lundbergh.cart.checkout_hint') }}
                        </x-lundbergh-bubble>
                    </div>
                @endif
            </x-command-panel>
        </flux:modal>
    @endteleport
</div>
