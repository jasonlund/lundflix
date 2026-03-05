<?php

use App\Models\PlexMediaServer;
use App\Models\Show;
use App\Services\ThirdParty\PlexService;
use App\Support\EpisodeCode;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public Show $show;

    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="flex items-center gap-1.5 rounded-lg border-1 border-zinc-600 bg-white/10 px-3 py-2 text-white backdrop-blur-sm">
            <div class="relative flex min-w-4 items-center justify-center">
                <span class="invisible">-</span>
                <flux:icon.loading class="absolute size-4 text-zinc-400" />
            </div>
            <flux:icon.play-circle class="size-4 text-zinc-400" />
        </div>
        HTML;
    }

    public function mount(): void
    {
        // Dispatch availability data immediately after mount
        $this->dispatch('plex-show-loaded', availability: $this->episodeAvailability());
    }

    #[Computed]
    public function servers(): Collection
    {
        $user = auth()->user();
        if (! $user?->plex_token || ! $this->show->imdb_id) {
            return collect();
        }

        return Cache::remember("plex:show:{$user->id}:{$this->show->id}", now()->addMinutes(10), function () use (
            $user,
        ) {
            $plex = app(PlexService::class);
            $externalGuid = "imdb://{$this->show->imdb_id}";

            return $plex->searchShowWithEpisodes($user->plex_token, $externalGuid);
        });
    }

    #[Computed]
    public function airedEpisodeCount(): int
    {
        return $this->show->episodes
            ->filter(fn ($episode): bool => ! empty($episode->airdate) && Carbon::parse($episode->airdate)->lte(today()))
            ->count();
    }

    /**
     * @return list<array{name: string, clientIdentifier: string, ownerThumb: string|null, episodeCount: int, hasAllAired: bool, tooltip: string}>
     */
    #[Computed]
    public function serverDisplayData(): array
    {
        if ($this->servers->isEmpty()) {
            return [];
        }

        $airedCount = $this->airedEpisodeCount;

        $clientIds = $this->servers->pluck('clientIdentifier')->all();
        $plexServers = PlexMediaServer::where('visible', true)
            ->whereIn('client_identifier', $clientIds)
            ->pluck('owner_thumb', 'client_identifier');

        return $this->servers
            ->filter(fn (array $server): bool => $plexServers->has($server['clientIdentifier']))
            ->map(function (array $server) use ($airedCount, $plexServers): array {
                $episodeCount = count($server['episodes']);
                $hasAllAired = $airedCount > 0 && $episodeCount >= $airedCount;

                $tooltip = $hasAllAired
                    ? "{$server['name']} — All episodes"
                    : "{$server['name']} — {$episodeCount} of {$airedCount} episodes";

                return [
                    'name' => $server['name'],
                    'clientIdentifier' => $server['clientIdentifier'],
                    'ownerThumb' => $plexServers->get($server['clientIdentifier']),
                    'episodeCount' => $episodeCount,
                    'hasAllAired' => $hasAllAired,
                    'tooltip' => $tooltip,
                ];
            })
            ->all();
    }

    /**
     * Transform server-centric data to episode-centric lookup.
     *
     * @return array<string, array<int, array{name: string, owned: bool}>>
     */
    public function episodeAvailability(): array
    {
        $availability = [];

        foreach ($this->servers as $server) {
            foreach ($server['episodes'] as $ep) {
                $code = strtoupper(EpisodeCode::generate($ep['season'], $ep['episode']));

                if (! isset($availability[$code])) {
                    $availability[$code] = [];
                }

                $availability[$code][] = [
                    'name' => $server['name'],
                    'owned' => $server['owned'],
                ];
            }
        }

        return $availability;
    }
};
?>

<div>
    @if (count($this->serverDisplayData) > 0)
        <div
            class="flex items-center gap-1.5 rounded-lg border-1 border-zinc-600 bg-white/10 px-2 py-1 backdrop-blur-sm"
        >
            <flux:avatar.group class="**:ring-transparent">
                @foreach ($this->serverDisplayData as $server)
                    <flux:avatar
                        wire:key="server-{{ $server['clientIdentifier'] }}"
                        size="sm"
                        :src="$server['ownerThumb']"
                        :name="$server['name']"
                        :tooltip="$server['tooltip']"
                        :badge="$server['hasAllAired'] ? '✓' : ($server['episodeCount'] > 99 ? '99+' : (string) $server['episodeCount'])"
                        badge:color="{{ $server['hasAllAired'] ? 'green' : 'zinc' }}"
                        badge:position="bottom left"
                    />
                @endforeach
            </flux:avatar.group>
            <flux:icon.play-circle class="size-4 text-white" />
        </div>
    @else
        <flux:tooltip content="Not on Plex">
            <div
                class="flex items-center gap-1.5 rounded-lg border-1 border-zinc-600 bg-white/10 px-3 py-2 text-white backdrop-blur-sm"
            >
                <div class="relative flex min-w-4 items-center justify-center">
                    <span class="invisible">-</span>
                    <flux:icon.x-mark variant="mini" class="absolute size-4 text-zinc-400" />
                </div>
                <flux:icon.play-circle class="size-4 text-zinc-400" />
            </div>
        </flux:tooltip>
    @endif
</div>
