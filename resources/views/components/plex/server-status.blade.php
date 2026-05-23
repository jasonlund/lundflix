<?php

use App\Models\PlexMediaServer;
use App\Support\UserTime;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
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

    public function cachedAtDiff(): string
    {
        return UserTime::toUserTz($this->getCachedAt())->shortAbsoluteDiffForHumans();
    }

    public function lastSeenDiff(PlexMediaServer $server): string
    {
        if (! $server->last_seen_at) {
            return '';
        }

        return UserTime::toUserTz($server->last_seen_at)->shortAbsoluteDiffForHumans() . ' ago';
    }
};
?>

<flux:card size="sm">
    <div class="flex items-center justify-between">
        <p class="font-semibold text-white">Servers</p>
        <flux:text size="xs" class="text-zinc-400">{{ $this->cachedAtDiff() }} ago</flux:text>
    </div>

    @if ($this->getServers()->isEmpty())
        <x-lundbergh-bubble variant="error" :message="__('lundbergh.error.no_servers')" />
    @else
        <x-dashboard.list>
            @foreach ($this->getServers() as $server)
                <x-dashboard.list-row
                    :href="$server->webUrl()"
                    :wire-key="'plex-server-' . $server->client_identifier"
                    :navigate="false"
                    target="_blank"
                    rel="noopener"
                >
                    <x-slot:leading>
                        <flux:avatar size="xs" circle :src="$server->owner_thumb" :name="$server->name" />
                        <span
                            @class([
                                'size-2 shrink-0 self-center rounded-full',
                                'bg-green-500' => $server->is_online,
                                'bg-red-500' => ! $server->is_online,
                            ])
                        ></span>
                    </x-slot>

                    <span class="block truncate font-serif tracking-wide">
                        {{ $server->name }}
                        @unless ($server->is_online)
                            <span class="ml-1 text-sm text-zinc-400">{{ $this->lastSeenDiff($server) }}</span>
                        @endunless
                    </span>

                    <x-slot:trailing>
                        <flux:icon name="arrow-top-right-on-square" variant="mini" class="shrink-0 text-zinc-400" />
                    </x-slot>
                </x-dashboard.list-row>
            @endforeach
        </x-dashboard.list>
    @endif
</flux:card>
