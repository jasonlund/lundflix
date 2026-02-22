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
        <flux:card size="sm">
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

<flux:card size="sm">
    <div class="flex items-center justify-between">
        <flux:heading size="lg">Servers</flux:heading>
        <flux:text size="xs" class="text-zinc-400">
            {{ $this->getCachedAt()->shortAbsoluteDiffForHumans() }} ago
        </flux:text>
    </div>

    @if ($this->getServers()->isEmpty())
        <flux:text class="mt-2 text-zinc-500">No servers available.</flux:text>
    @else
        <flux:table class="mt-3">
            <flux:table.rows>
                @foreach ($this->getServers() as $server)
                    <flux:table.row :key="$server->client_identifier">
                        <flux:table.cell variant="strong">
                            <div class="flex items-center gap-2">
                                <flux:avatar size="xs" circle :src="$server->owner_thumb" :name="$server->name" />
                                {{ $server->name }}
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <div
                                    class="{{ $server->is_online ? 'bg-green-500' : 'bg-red-500' }} size-2 shrink-0 rounded-full"
                                ></div>
                                @unless ($server->is_online)
                                    <flux:text size="xs">
                                        {{ $server->last_seen_at?->shortAbsoluteDiffForHumans() }} ago
                                    </flux:text>
                                @endunless
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button
                                variant="ghost"
                                size="sm"
                                icon="arrow-top-right-on-square"
                                href="{{ $server->webUrl() }}"
                                target="_blank"
                                inset="top bottom"
                            />
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</flux:card>
