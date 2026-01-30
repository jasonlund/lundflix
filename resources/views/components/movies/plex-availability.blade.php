<?php

use App\Models\Movie;
use App\Services\PlexService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public Movie $movie;

    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="mt-6">
            <flux:heading size="lg">Plex Availability</flux:heading>
            <flux:text class="mt-2 text-zinc-500">Checking servers...</flux:text>
        </div>
        HTML;
    }

    #[Computed]
    public function servers(): Collection
    {
        $user = auth()->user();
        if (! $user?->plex_token) {
            return collect();
        }

        $plex = app(PlexService::class);
        $externalGuid = "imdb://{$this->movie->imdb_id}";

        return $plex->searchByExternalId($user->plex_token, $externalGuid, 1);
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
                    @if ($server['owned'])
                        <flux:badge size="sm" color="zinc">Owned</flux:badge>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
