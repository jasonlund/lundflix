<?php

use App\Jobs\StoreShowEpisodes;
use App\Models\Episode;
use App\Models\Show;
use App\Services\CartService;
use App\Services\TVMazeService;
use App\Support\EpisodeCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Episode list component using wire:init deferred loading pattern.
 *
 * We use wire:init instead of #[Lazy] because episodes may not exist in the database
 * yet, requiring an API request to TVMaze. The wire:init pattern allows us to use
 * wire:loading.delay to debounce the skeleton loader, preventing flicker on fast loads
 * while still showing feedback during slow API requests.
 */
new class extends Component {
    public Show $show;

    public bool $readyToLoad = false;

    /** @var array<int, array<int, array>> */
    public array $episodesBySeason = [];

    /** @var array<string, array> Map of episode codes to episode arrays for O(1) lookup */
    private array $episodesByCode = [];

    /** @var array<string> Episode codes currently in cart for this show */
    public array $selectedEpisodes = [];

    public function loadEpisodes(TVMazeService $tvMaze, CartService $cart): void
    {
        // Check DB first
        $dbEpisodes = $this->show->episodes;

        if ($dbEpisodes->isNotEmpty()) {
            $this->episodesBySeason = $this->groupBySeason($dbEpisodes->toArray());
        } else {
            // Fetch from API (may be slow on first load)
            $apiEpisodes = $tvMaze->episodes($this->show->tvmaze_id) ?? [];
            $this->episodesBySeason = $this->groupBySeason($apiEpisodes);

            // Queue storage
            if (! empty($apiEpisodes)) {
                StoreShowEpisodes::dispatch($this->show, $apiEpisodes);
            }
        }

        $this->buildEpisodeCodeMap();
        $this->refreshSelectedEpisodes($cart);
        $this->readyToLoad = true;
    }

    /**
     * Rebuild the episode code map on every request.
     * Private properties aren't persisted across Livewire requests.
     */
    public function boot(): void
    {
        $this->buildEpisodeCodeMap();
    }

    /**
     * Build the episode code lookup map for O(1) access.
     */
    private function buildEpisodeCodeMap(): void
    {
        $this->episodesByCode = [];
        foreach ($this->episodesBySeason as $episodes) {
            foreach ($episodes as $episode) {
                $code = $this->getEpisodeCode($episode);
                $this->episodesByCode[$code] = $episode;
            }
        }
    }

    #[On('cart-updated')]
    public function onCartUpdated(CartService $cart): void
    {
        $this->refreshSelectedEpisodes($cart);
    }

    public function refreshSelectedEpisodes(CartService $cart): void
    {
        $this->selectedEpisodes = [];
        $cartItems = $cart->items();

        foreach ($cartItems['episodes'] as $entry) {
            if ($entry['show_id'] === $this->show->id) {
                $this->selectedEpisodes[] = $entry['code'];
            }
        }
    }

    /**
     * Lifecycle hook called when selectedEpisodes changes via wire:model.
     * Syncs checkbox state with the cart.
     */
    public function updatedSelectedEpisodes(): void
    {
        $cart = app(CartService::class);
        $currentCartCodes = [];

        foreach ($cart->items()['episodes'] as $entry) {
            if ($entry['show_id'] === $this->show->id) {
                $currentCartCodes[] = $entry['code'];
            }
        }

        $codesToAdd = array_diff($this->selectedEpisodes, $currentCartCodes);
        $codesToRemove = array_diff($currentCartCodes, $this->selectedEpisodes);

        foreach ($codesToAdd as $code) {
            $episode = $this->findEpisodeByCode($code);
            if ($episode) {
                $cart->add($this->cartItemFor($episode));
            }
        }

        foreach ($codesToRemove as $code) {
            $episode = $this->findEpisodeByCode($code);
            if ($episode) {
                $cart->remove($this->cartItemFor($episode));
            }
        }

        if (! empty($codesToAdd) || ! empty($codesToRemove)) {
            $this->dispatch('cart-updated');
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findEpisodeByCode(string $code): ?array
    {
        return $this->episodesByCode[$code] ?? null;
    }

    /**
     * @param  array<string, mixed>  $episode
     */
    public function isSpecialEpisode(array $episode): bool
    {
        return ($episode['type'] ?? 'regular') === 'significant_special';
    }

    /**
     * @param  array<string, mixed>  $episode
     */
    public function isFutureEpisode(array $episode): bool
    {
        return $episode['airdate'] && \Carbon\Carbon::parse($episode['airdate'])->gt(now());
    }

    /**
     * @param  array<string, mixed>  $episode
     */
    public function toggleEpisode(array $episode, CartService $cart): void
    {
        $cartItem = $this->cartItemFor($episode);

        if ($this->isEpisodeSelected($episode)) {
            $cart->remove($cartItem);
        } else {
            $cart->add($cartItem);
        }

        $this->refreshSelectedEpisodes($cart);
        $this->dispatch('cart-updated');
    }

    public function toggleSeason(int $season, CartService $cart): void
    {
        $seasonEpisodes = $this->episodesBySeason[$season] ?? [];

        // Filter out future episodes - they can't be added to cart
        $availableEpisodes = array_filter($seasonEpisodes, fn ($ep) => ! $this->isFutureEpisode($ep));

        if ($this->isSeasonFullySelected($season)) {
            // Remove all episodes in this season
            foreach ($availableEpisodes as $episode) {
                $cartItem = $this->cartItemFor($episode);
                $cart->remove($cartItem);
            }
        } else {
            // Add all unselected episodes in this season
            foreach ($availableEpisodes as $episode) {
                if (! $this->isEpisodeSelected($episode)) {
                    $cartItem = $this->cartItemFor($episode);
                    $cart->add($cartItem);
                }
            }
        }

        $this->refreshSelectedEpisodes($cart);
        $this->dispatch('cart-updated');
    }

    /**
     * @param  array<string, mixed>  $episode
     */
    public function isEpisodeSelected(array $episode): bool
    {
        $code = $this->getEpisodeCode($episode);

        return in_array($code, $this->selectedEpisodes);
    }

    public function isSeasonFullySelected(int $season): bool
    {
        $seasonEpisodes = $this->episodesBySeason[$season] ?? [];

        // Filter out future episodes - they can't be selected
        $availableEpisodes = array_filter($seasonEpisodes, fn ($ep) => ! $this->isFutureEpisode($ep));

        if (empty($availableEpisodes)) {
            return false;
        }

        foreach ($availableEpisodes as $episode) {
            if (! $this->isEpisodeSelected($episode)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $episode
     */
    public function getEpisodeCode(array $episode): string
    {
        $isSpecial = ($episode['type'] ?? 'regular') === 'significant_special';

        return EpisodeCode::generate($episode['season'], $episode['number'], $isSpecial);
    }

    /**
     * @param  array<int, array<string, mixed>>  $episodes
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function groupBySeason(array $episodes): array
    {
        // Filter out insignificant specials
        $filtered = collect($episodes)->filter(fn (array $ep) => ($ep['type'] ?? 'regular') !== 'insignificant_special');

        // Assign display numbers to significant specials within each season
        $processed = $filtered->groupBy('season')->map(function (Collection $seasonEps) {
            // Separate regular and special episodes
            $regular = $seasonEps->filter(fn ($ep) => ($ep['type'] ?? 'regular') === 'regular');
            $specials = $seasonEps->filter(fn ($ep) => ($ep['type'] ?? 'regular') === 'significant_special');

            // Sort specials by airdate, then tvmaze_id, and assign numbers
            if ($specials->isNotEmpty()) {
                $specials = $specials
                    ->sort(EpisodeCode::compareForSorting(...))
                    ->values()
                    ->map(function ($ep, $index) {
                        // Only assign number if not already set (API episodes have null)
                        if ($ep['number'] === null) {
                            $ep['number'] = $index + 1;
                        }

                        return $ep;
                    });
            }

            // Merge and sort: regular first by number, then specials
            return $regular
                ->sortBy('number')
                ->values()
                ->concat($specials)
                ->all();
        });

        return $processed->sortKeys()->all();
    }

    /**
     * Get the cart item for an episode (Model for DB episodes, array for API episodes).
     *
     * @param  array<string, mixed>  $episode
     * @return Model|array<string, mixed>
     */
    public function cartItemFor(array $episode): Model|array
    {
        // DB episodes have show_id, API episodes don't
        if (isset($episode['show_id'])) {
            return Episode::find($episode['id']);
        }

        return array_merge($episode, ['show_id' => $this->show->id]);
    }
};
?>

<div class="mt-8" wire:init="loadEpisodes">
    {{-- Skeleton loader with debounced delay - only shows if loading takes >200ms --}}
    <div wire:loading.delay wire:target="loadEpisodes">
        <flux:heading size="lg">Episodes</flux:heading>
        <div class="mt-4 animate-pulse space-y-2">
            <div class="h-8 w-32 rounded bg-zinc-700"></div>
            <div class="h-12 rounded bg-zinc-800"></div>
            <div class="h-12 rounded bg-zinc-800"></div>
            <div class="h-12 rounded bg-zinc-800"></div>
        </div>
    </div>

    @if ($readyToLoad)
        <flux:heading size="lg">Episodes</flux:heading>

        @if (count($episodesBySeason) > 0)
            <flux:accordion transition class="mt-6">
                @foreach ($episodesBySeason as $season => $episodes)
                    <flux:accordion.item
                        wire:key="season-{{ $season }}"
                        :expanded="$season === $show->most_recent_season"
                    >
                        <flux:accordion.heading>
                            <div class="flex w-full items-center justify-between pr-2">
                                <span>Season {{ $season }}</span>
                                <flux:button
                                    wire:click.stop="toggleSeason({{ $season }})"
                                    :variant="$this->isSeasonFullySelected($season) ? 'danger' : 'primary'"
                                    size="xs"
                                >
                                    {{ $this->isSeasonFullySelected($season) ? 'Remove Season' : 'Request Season' }}
                                </flux:button>
                            </div>
                        </flux:accordion.heading>

                        <flux:accordion.content>
                            <div class="space-y-2">
                                @foreach ($episodes as $episode)
                                    <div
                                        wire:key="episode-{{ $episode['tvmaze_id'] ?? $episode['id'] }}"
                                        class="flex items-center gap-4 rounded-lg bg-zinc-800 p-3"
                                    >
                                        @if ($this->isFutureEpisode($episode))
                                            <div class="w-6"></div>
                                        @else
                                            <flux:checkbox
                                                wire:model.live="selectedEpisodes"
                                                value="{{ $this->getEpisodeCode($episode) }}"
                                            />
                                        @endif

                                        <div class="w-12 shrink-0 text-center">
                                            <flux:text class="text-lg font-medium">
                                                {{ $this->isSpecialEpisode($episode) ? 'S' : '' }}{{ $episode['number'] }}
                                            </flux:text>
                                        </div>

                                        <div class="min-w-0 flex-1">
                                            <flux:text class="font-medium">{{ $episode['name'] }}</flux:text>
                                            @if ($episode['airdate'])
                                                <flux:text class="text-sm text-zinc-400">
                                                    {{ \Carbon\Carbon::parse($episode['airdate'])->format('M j, Y') }}
                                                </flux:text>
                                            @endif
                                        </div>

                                        @if ($episode['runtime'])
                                            <flux:text class="text-sm text-zinc-400">
                                                {{ $episode['runtime'] }} min
                                            </flux:text>
                                        @endif

                                        @if ($this->isFutureEpisode($episode))
                                            <flux:button disabled variant="filled" icon="clock" size="sm">
                                                Not Yet Aired
                                            </flux:button>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </flux:accordion.content>
                    </flux:accordion.item>
                @endforeach
            </flux:accordion>
        @else
            <flux:text class="mt-4 text-zinc-400">No episodes available.</flux:text>
        @endif
    @endif
</div>
