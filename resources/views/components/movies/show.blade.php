<?php

use App\Models\Movie;
use App\Support\Formatters;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Movie $movie;

    public function mount(Movie $movie): void
    {
        $this->movie = $movie;
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
        <flux:button as="a" href="{{ route('home') }}" wire:navigate variant="ghost" icon="arrow-left" />
        <flux:heading size="xl">{{ $this->movie->title }}</flux:heading>
    </div>

    <div class="flex flex-wrap items-center gap-3 text-zinc-400">
        @if ($this->movie->year)
            <flux:text>{{ $this->movie->year }}</flux:text>
            <span>&middot;</span>
        @endif

        @php($runtime = Formatters::runtime($this->movie->runtime))
        @if ($runtime)
            <flux:text>{{ $runtime }}</flux:text>
            <span>&middot;</span>
        @endif

        @if ($this->movie->num_votes)
            <flux:text>{{ $this->formattedVotes() }} votes</flux:text>
        @endif
    </div>

    @if ($this->movie->genres && count($this->movie->genres) > 0)
        <div class="flex flex-wrap gap-2">
            @foreach ($this->movie->genres as $genre)
                <x-genre-badge :$genre />
            @endforeach
        </div>
    @endif

    <div class="flex gap-3 pt-4">
        <flux:button as="a" href="{{ $this->imdbUrl() }}" target="_blank" icon="external-link">
            View on IMDB
        </flux:button>
        <livewire:cart.add-movie-button :movie="$movie" />
    </div>

    <livewire:movies.plex-availability :movie="$movie" lazy />
</div>
