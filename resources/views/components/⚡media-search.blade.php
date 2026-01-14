<?php

use App\Models\Movie;
use App\Models\Show;
use Illuminate\Support\Collection;
use Livewire\Component;

new class extends Component {
    public string $query = '';

    public function results(): Collection
    {
        if (strlen($this->query) < 2) {
            return collect();
        }

        $term = '%' . $this->query . '%';

        $shows = Show::query()
            ->where('name', 'like', $term)
            ->limit(10)
            ->get()
            ->map(fn ($s) => ['type' => 'show', 'id' => $s->id, 'title' => $s->name]);

        $movies = Movie::query()
            ->where('title', 'like', $term)
            ->orderByDesc('year')
            ->limit(10)
            ->get()
            ->map(fn ($m) => ['type' => 'movie', 'id' => $m->id, 'title' => $m->title, 'year' => $m->year]);

        return $shows->concat($movies)->take(15);
    }
};
?>

<div class="w-full max-w-xl">
    <flux:command>
        <flux:command.input wire:model.live.debounce.300ms="query" placeholder="Search shows & movies..." clearable />
        <flux:command.items>
            @forelse ($this->results() as $result)
                <flux:command.item icon="{{ $result['type'] === 'show' ? 'tv' : 'film' }}">
                    {{ $result['title'] }}
                    @if ($result['type'] === 'movie' && $result['year'])
                        <span class="text-zinc-400">({{ $result['year'] }})</span>
                    @endif
                </flux:command.item>
            @empty
                @if (strlen($query) >= 2)
                    <div class="px-4 py-3 text-sm text-zinc-500">No results found</div>
                @endif
            @endforelse
        </flux:command.items>
    </flux:command>
</div>
