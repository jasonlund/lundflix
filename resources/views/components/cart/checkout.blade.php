<?php

use App\Actions\Request\CreateRequest;
use App\Actions\Request\CreateRequestItems;
use App\Enums\MediaType;
use App\Models\Episode;
use App\Services\CartService;
use App\Support\CartItemFormatter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Layout('components.layouts.app')] class extends Component {
    #[Validate('nullable|string|max:1000')]
    public string $notes = '';

    #[Computed]
    public function groupedCartItems(): array
    {
        return app(CartService::class)->loadGroupedItems();
    }

    #[Computed]
    public function cartCount(): int
    {
        return app(CartService::class)->count();
    }

    public function removeMovie(int $id): void
    {
        app(CartService::class)->remove($id);
        unset($this->groupedCartItems, $this->cartCount);
        $this->dispatch('cart-updated');
    }

    /**
     * Remove a group of episodes (a run or full season).
     *
     * @param  array<int>  $episodeIds
     */
    public function removeEpisodes(array $episodeIds): void
    {
        if (empty($episodeIds)) {
            return;
        }

        $cart = app(CartService::class);
        $episodes = Episode::whereIn('id', $episodeIds)->get();

        // Only remove episodes that exist and are in the cart
        $removedAny = false;
        foreach ($episodes as $episode) {
            if ($cart->has($episode)) {
                $cart->remove($episode);
                $removedAny = true;
            }
        }

        if ($removedAny) {
            unset($this->groupedCartItems, $this->cartCount);
            $this->dispatch('cart-updated');
        }
    }

    public function submit(CreateRequest $createRequest, CreateRequestItems $createRequestItems): void
    {
        $this->validate();

        $cart = app(CartService::class);

        if ($cart->isEmpty()) {
            $this->addError('cart', 'Your cart is empty.');

            return;
        }

        DB::transaction(function () use ($cart, $createRequest, $createRequestItems) {
            $request = $createRequest->create(Auth::user(), $this->notes ?: null);

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
        });

        $this->dispatch('cart-updated');

        session()->flash('message', 'Your request has been submitted!');
        $this->redirect(route('home'), navigate: true);
    }

    /**
     * Format a run of episodes for display.
     *
     * @param  Collection<int, Episode>  $episodes
     */
    public function formatRun(Collection $episodes): string
    {
        return CartItemFormatter::formatRun($episodes);
    }

    /**
     * Format a season label for display.
     */
    public function formatSeason(int $season): string
    {
        return CartItemFormatter::formatSeason($season);
    }
};
?>

<div class="space-y-6">
    <div class="flex items-center gap-4">
        <flux:button as="a" href="{{ route('home') }}" variant="ghost" icon="arrow-left" />
        <flux:heading size="xl">Checkout</flux:heading>
    </div>

    @if (session('message'))
        <flux:callout variant="success">{{ session('message') }}</flux:callout>
    @endif

    @error('cart')
        <flux:callout variant="danger">{{ $message }}</flux:callout>
    @enderror

    @if ($this->cartCount === 0)
        <div class="rounded-lg bg-zinc-800 p-8 text-center">
            <flux:icon name="shopping-cart" class="mx-auto mb-4 size-12 text-zinc-500" />
            <flux:heading size="lg">Your cart is empty</flux:heading>
            <flux:text class="mt-2 text-zinc-400">Search for movies and shows to add to your request.</flux:text>
            <flux:button as="a" href="{{ route('home') }}" variant="primary" class="mt-4">Browse Content</flux:button>
        </div>
    @else
        <div class="space-y-3">
            {{-- Movies --}}
            @foreach ($this->groupedCartItems['movies'] as $movie)
                <div
                    wire:key="checkout-movie-{{ $movie->id }}"
                    class="flex items-center gap-4 rounded-lg bg-zinc-800 p-4"
                >
                    <flux:icon name="film" class="size-6 shrink-0 text-zinc-400" />
                    <div class="min-w-0 flex-1">
                        <flux:text class="font-medium">
                            {{ $movie->title }}
                            @if ($movie->year)
                                <span class="text-zinc-400">({{ $movie->year }})</span>
                            @endif
                        </flux:text>
                    </div>
                    <flux:button wire:click="removeMovie({{ $movie->id }})" variant="ghost" icon="x-mark" size="sm" />
                </div>
            @endforeach

            {{-- Shows with episodes --}}
            @foreach ($this->groupedCartItems['shows'] as $showGroup)
                <div wire:key="checkout-show-{{ $showGroup['show']->id }}" class="rounded-lg bg-zinc-800 p-4">
                    <div class="mb-3 flex items-center gap-3">
                        <flux:icon name="tv" class="size-6 shrink-0 text-zinc-400" />
                        <flux:text class="font-medium">{{ $showGroup['show']->name }}</flux:text>
                    </div>

                    <div class="ml-9 space-y-2">
                        @foreach ($showGroup['seasons'] as $seasonData)
                            @if ($seasonData['is_full'])
                                {{-- Full season --}}
                                <div
                                    wire:key="checkout-show-{{ $showGroup['show']->id }}-season-{{ $seasonData['season'] }}"
                                    class="flex items-center justify-between rounded bg-zinc-700/50 px-3 py-2"
                                >
                                    <flux:text class="text-sm">
                                        {{ $this->formatSeason($seasonData['season']) }}
                                        <span class="text-zinc-400">
                                            ({{ $seasonData['episodes']->count() }} episodes)
                                        </span>
                                    </flux:text>
                                    <flux:button
                                        wire:click="removeEpisodes({{ json_encode($seasonData['episodes']->pluck('id')->all()) }})"
                                        variant="ghost"
                                        icon="x-mark"
                                        size="xs"
                                    />
                                </div>
                            @else
                                {{-- Individual runs --}}
                                @foreach ($seasonData['runs'] as $runIndex => $run)
                                    <div
                                        wire:key="checkout-show-{{ $showGroup['show']->id }}-season-{{ $seasonData['season'] }}-run-{{ $runIndex }}"
                                        class="flex items-center justify-between rounded bg-zinc-700/50 px-3 py-2"
                                    >
                                        <flux:text class="text-sm">
                                            {{ $this->formatRun($run) }}
                                            @if ($run->count() > 1)
                                                <span class="text-zinc-400">({{ $run->count() }} episodes)</span>
                                            @endif
                                        </flux:text>
                                        <flux:button
                                            wire:click="removeEpisodes({{ json_encode($run->pluck('id')->all()) }})"
                                            variant="ghost"
                                            icon="x-mark"
                                            size="xs"
                                        />
                                    </div>
                                @endforeach
                            @endif
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <div class="space-y-4 pt-4">
            <flux:field>
                <flux:textarea
                    wire:model.blur="notes"
                    label="Notes (optional)"
                    placeholder="Any special requests or notes..."
                    rows="3"
                />
                <flux:error name="notes" />
            </flux:field>

            <flux:button wire:click="submit" variant="primary" class="w-full">
                Submit Request ({{ $this->cartCount }} items)
            </flux:button>
        </div>
    @endif
</div>
