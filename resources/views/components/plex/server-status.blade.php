<?php

use App\Models\PlexMediaServer;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
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
     * @return Collection<int, PlexMediaServer>
     */
    #[Computed]
    public function servers(): Collection
    {
        return PlexMediaServer::where('visible', true)->get();
    }
};
?>

<flux:card>
    <flux:heading size="lg">Plex Servers</flux:heading>

    @if ($this->servers->isEmpty())
        <flux:text class="mt-2 text-zinc-500">No servers available.</flux:text>
    @else
        <div class="mt-3 space-y-2">
            @foreach ($this->servers as $server)
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
</flux:card>
