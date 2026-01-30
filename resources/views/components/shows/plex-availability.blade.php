<?php

use App\Models\Show;
use App\Services\PlexService;
use App\Support\EpisodeCode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public Show $show;

    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="mt-6">
            <flux:heading size="lg">Plex Availability</flux:heading>
            <flux:text class="mt-2 text-zinc-500">Checking servers...</flux:text>
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

<div class="mt-6">
    <flux:heading size="lg">Plex Availability</flux:heading>

    @if ($this->servers->isEmpty())
        <flux:text class="mt-2 text-zinc-500">Not available on any servers.</flux:text>
    @else
        <div class="mt-3 space-y-2">
            @foreach ($this->servers as $server)
                <div wire:key="server-{{ $server['clientIdentifier'] }}" class="flex items-center gap-2">
                    <flux:icon.circle-check variant="mini" class="text-green-500" />
                    <flux:text>{{ $server['name'] }}</flux:text>
                    <flux:badge size="sm" color="zinc">{{ count($server['episodes']) }} episodes</flux:badge>
                    @if ($server['owned'])
                        <flux:badge size="sm" color="zinc">Owned</flux:badge>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
