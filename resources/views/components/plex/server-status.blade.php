<?php

use App\Models\PlexMediaServer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public function placeholder(): string
    {
        return <<<'HTML'
        <flux:card>
            <flux:heading size="lg">Plex Servers</flux:heading>
            <flux:text class="mt-2 text-zinc-500">Checking server status...</flux:text>
        </flux:card>
        HTML;
    }

    /**
     * @return array{servers: Collection<int, PlexMediaServer>, cached_at: Carbon}
     */
    #[Computed]
    public function serverData(): array
    {
        return Cache::remember(
            'plex:visible-servers',
            now()->addMinutes(10),
            fn () => [
                'servers' => PlexMediaServer::where('visible', true)->get(),
                'cached_at' => now(),
            ],
        );
    }

    /**
     * @return Collection<int, PlexMediaServer>
     */
    public function getServers(): Collection
    {
        return $this->serverData['servers'];
    }

    public function getCachedAt(): Carbon
    {
        return $this->serverData['cached_at'];
    }
};
?>

<flux:card>
    <flux:heading size="lg">Plex Servers</flux:heading>

    @if ($this->getServers()->isEmpty())
        <flux:text class="mt-2 text-zinc-500">No servers available.</flux:text>
    @else
        <div class="mt-3 space-y-2">
            @foreach ($this->getServers() as $server)
                <div wire:key="server-{{ $server->client_identifier }}" class="flex items-center gap-2">
                    @if ($server->is_online)
                        <flux:icon.check-circle variant="mini" class="text-green-500" />
                    @else
                        <flux:icon.x-circle variant="mini" class="text-red-500" />
                    @endif
                    <flux:text>{{ $server->name }}</flux:text>
                    @if ($server->owned)
                        <flux:badge size="sm" color="zinc">Owned</flux:badge>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <flux:text size="xs" class="mt-3 text-zinc-400">Updated {{ $this->getCachedAt()->diffForHumans() }}</flux:text>
</flux:card>
