<?php

use App\Actions\Request\CreateRequest;
use App\Actions\Request\CreateRequestItems;
use App\Events\RequestSubmitted;
use App\Services\CartService;
use App\Support\Formatters;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public ?array $movies = null;
    public ?array $episodes = null;

    #[Computed]
    public function groupedItems(): ?array
    {
        if ($this->movies === null && $this->episodes === null) {
            return null;
        }

        $cartService = app(CartService::class);
        return $cartService->loadGroupedItemsFromIds($this->movies ?? [], $this->episodes ?? []);
    }

    public function formatRun(Collection $episodes): string
    {
        return Formatters::formatRun($episodes);
    }

    public function formatSeason(int $season): string
    {
        return Formatters::formatSeason($season);
    }

    #[On('open-cart')]
    public function openCart(array $movies, array $episodes): void
    {
        $this->movies = $movies;
        $this->episodes = $episodes;
        $this->modal('cart')->show();
    }

    #[On('cart-movie-toggled')]
    public function onMovieToggled(string $text): void
    {
        Flux::toast(text: $text);
    }

    #[On('cart-episodes-synced')]
    public function onEpisodesSynced(int $showId, string $showName, int $delta, string $toastKey): void
    {
        if ($toastKey === 'episodes_added') {
            Flux::toast(text: trans_choice('lundbergh.toast.episodes_added', $delta, ['title' => $showName]));
        } elseif ($toastKey === 'episodes_removed') {
            Flux::toast(text: trans_choice('lundbergh.toast.episodes_removed', $delta, ['title' => $showName]));
        } else {
            Flux::toast(text: __('lundbergh.toast.episodes_swapped', ['title' => $showName]));
        }
    }

    public function submit(
        CreateRequest $createRequest,
        CreateRequestItems $createRequestItems,
        CartService $cartService,
    ): void {
        if (empty($this->movies) && empty($this->episodes)) {
            return;
        }

        $items = $cartService->loadItemsFromIds($this->movies ?? [], $this->episodes ?? []);
        $count = $items->count();

        $request = DB::transaction(function () use ($items, $createRequest, $createRequestItems) {
            $request = $createRequest->create(Auth::user());

            $createRequestItems->create(
                $request,
                $items
                    ->map(
                        fn ($item) => [
                            'type' => $item->getMediaType(),
                            'id' => $item->id,
                        ],
                    )
                    ->all(),
            );

            return $request;
        });

        RequestSubmitted::dispatch($request);

        $this->modal('cart')->close();
        $this->dispatch('cart-submitted');

        Flux::toast(
            text: trans_choice('lundbergh.toast.request_submitted', $count, ['count' => $count]),
            variant: 'success',
        );

        $this->redirect(route('home'), navigate: true);
    }
};
?>

<div x-data>
    <flux:button
        variant="ghost"
        x-on:click="$dispatch('open-cart', $store.cart.toPayload())"
    >
        <flux:icon name="shopping-cart" x-show="$store.cart.count === 0" x-cloak class="text-lundflix size-4" />
        <span
            x-show="$store.cart.count > 0"
            x-cloak
            class="text-lundflix inline-flex size-4 items-center justify-center text-sm font-bold tabular-nums"
            x-text="$store.cart.count"
        ></span>
        <span class="sr-only sm:not-sr-only">Cart</span>
    </flux:button>

    @teleport('body')
        <flux:modal
            name="cart"
            variant="bare"
            class="m-0 h-dvh min-h-dvh w-full max-w-none p-0 md:mx-auto md:max-w-screen-md"
        >
            @if ($this->groupedItems !== null)
                <x-command-panel
                    name="cart"
                    panelClass="h-full bg-zinc-800/75 backdrop-blur-sm"
                    itemsClass="divide-y divide-zinc-700/70 overflow-y-auto"
                    :hasItems="true"
                >
                    <x-slot:header>
                        <div class="flex min-w-0 flex-1 items-center gap-2">
                            <flux:icon name="shopping-cart" variant="mini" class="shrink-0 text-zinc-400" />
                            <span class="text-sm font-medium text-white">
                                Your Cart
                                ({{ count($this->groupedItems['movies']) + collect($this->groupedItems['shows'])->sum(fn ($g) => count($g['seasons'])) }})
                            </span>
                        </div>
                    </x-slot>

                    <x-slot:empty>
                        <div class="px-3 py-4">
                            <x-lundbergh-bubble :with-margin="false">
                                {{ __('lundbergh.empty.cart') }}
                            </x-lundbergh-bubble>
                        </div>
                    </x-slot>

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

                    {{-- Movies --}}
                    @foreach ($this->groupedItems['movies'] as $movie)
                        <a
                            wire:key="cart-item-movie-{{ $movie->id }}"
                            href="{{ route('movies.show', $movie) }}"
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
                                        size="w200"
                                        class="h-full w-full overflow-hidden"
                                    />
                                </div>

                                <div class="flex min-w-0 flex-1 flex-col gap-1">
                                    <p class="truncate font-serif text-base leading-snug tracking-wide text-white">
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
                    @foreach ($this->groupedItems['shows'] as $showGroup)
                        <a
                            wire:key="cart-item-show-{{ $showGroup['show']->id }}"
                            href="{{ route('shows.show', $showGroup['show']) }}"
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
                                        size="w200"
                                        class="h-full w-full overflow-hidden"
                                    />
                                </div>

                                <div class="flex min-w-0 flex-1 flex-col gap-1">
                                    <p class="truncate font-serif text-base leading-snug tracking-wide text-white">
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

                    <div class="border-b-0 px-3 py-2">
                        <x-lundbergh-bubble :with-margin="false" contentTag="div">
                            {{ __('lundbergh.cart.checkout_hint') }}
                        </x-lundbergh-bubble>
                    </div>
                </x-command-panel>
            @endif
        </flux:modal>
    @endteleport
</div>
