<?php

use App\Models\Movie;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Movie $movie;

    public function mount(Movie $movie): void
    {
        $this->movie = $movie;
    }

    public function formattedRuntime(): ?string
    {
        if (! $this->movie->runtime) {
            return null;
        }

        $hours = intdiv($this->movie->runtime, 60);
        $minutes = $this->movie->runtime % 60;

        if ($hours > 0 && $minutes > 0) {
            return "{$hours}h {$minutes}m";
        }

        if ($hours > 0) {
            return "{$hours}h";
        }

        return "{$minutes}m";
    }

    public function genresArray(): array
    {
        if (! $this->movie->genres) {
            return [];
        }

        return array_map('trim', explode(',', $this->movie->genres));
    }

    public function formattedVotes(): string
    {
        return number_format($this->movie->num_votes ?? 0);
    }

    public function imdbUrl(): string
    {
        return "https://www.imdb.com/title/{$this->movie->imdb_id}/";
    }
};
?>

<div class="space-y-6">
    <div class="flex items-center gap-4">
        <flux:button as="a" href="{{ route('home') }}" variant="ghost" icon="arrow-left" />
        <flux:heading size="xl">{{ $this->movie->title }}</flux:heading>
    </div>

    <div class="flex flex-wrap items-center gap-3 text-zinc-500 dark:text-zinc-400">
        @if ($this->movie->year)
            <flux:text>{{ $this->movie->year }}</flux:text>
            <span>&middot;</span>
        @endif

        @if ($this->formattedRuntime())
            <flux:text>{{ $this->formattedRuntime() }}</flux:text>
            <span>&middot;</span>
        @endif

        @if ($this->movie->num_votes)
            <flux:text>{{ $this->formattedVotes() }} votes</flux:text>
        @endif
    </div>

    @if (count($this->genresArray()) > 0)
        <div class="flex flex-wrap gap-2">
            @foreach ($this->genresArray() as $genre)
                <flux:badge>{{ $genre }}</flux:badge>
            @endforeach
        </div>
    @endif

    <div class="pt-4">
        <flux:button as="a" href="{{ $this->imdbUrl() }}" target="_blank" icon="arrow-top-right-on-square">
            View on IMDB
        </flux:button>
    </div>
</div>
