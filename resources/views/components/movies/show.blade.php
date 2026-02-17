<?php

use App\Enums\Language;
use App\Models\Movie;
use App\Support\Formatters;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public Movie $movie;

    public function mount(Movie $movie): void
    {
        $this->movie = $movie;
    }

    public function imdbUrl(): string
    {
        return "https://www.imdb.com/title/{$this->movie->imdb_id}/";
    }

    public function releaseDate(): ?string
    {
        if ($this->movie->release_date) {
            return $this->movie->release_date->format('m/d/y');
        }

        if ($this->movie->year) {
            return (string) $this->movie->year;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function productionCompanyNames(): array
    {
        $companies = $this->movie->production_companies ?? [];

        return collect($companies)
            ->take(3)
            ->pluck('name')
            ->all();
    }

    public function formattedLanguage(): ?string
    {
        $original = $this->movie->original_language;
        if (! $original) {
            return null;
        }

        $label = $original->getLabel();

        $others = collect($this->movie->spoken_languages ?? [])
            ->reject(fn (Language $lang): bool => $lang === $original)
            ->take(3)
            ->map(fn (Language $lang): string => $lang->getLabel())
            ->all();

        if (count($others) === 0) {
            return $label;
        }

        return $label . ' (' . implode(', ', $others) . ')';
    }

    private const ENGLISH_COUNTRIES = ['US', 'GB', 'AU', 'CA'];

    public function titleVariants(): ?string
    {
        $parts = [];

        $originalTitle = $this->movie->original_title;
        if ($originalTitle && $originalTitle !== $this->movie->title) {
            $parts[] = 'Originally "' . $originalTitle . '"';
        }

        $altTitles = $this->alternativeTitles();
        if (count($altTitles) > 0) {
            $parts[] = 'aka ' . implode(', ', $altTitles);
        }

        return $parts ? implode(' · ', $parts) : null;
    }

    /**
     * @return list<string>
     */
    public function alternativeTitles(): array
    {
        $titles = $this->movie->alternative_titles ?? [];
        $movieTitle = $this->movie->title;

        return collect($titles)
            ->filter(fn (array $t): bool => in_array($t['iso_3166_1'] ?? '', self::ENGLISH_COUNTRIES))
            ->pluck('title')
            ->filter(fn (string $title): bool => $title !== $movieTitle)
            ->unique()
            ->take(2)
            ->values()
            ->all();
    }

    #[Computed]
    public function backgroundUrl(): ?string
    {
        return $this->movie->artUrl('background');
    }

    #[Computed]
    public function logoUrl(): ?string
    {
        return $this->movie->artUrl('logo');
    }

    public function contentRating(): ?string
    {
        return $this->movie->contentRating();
    }

    public function formattedRuntime(): ?string
    {
        return Formatters::runtime($this->movie->runtime);
    }

    public function render(): mixed
    {
        return $this->view()->layout('components.layouts.app', [
            'backgroundImage' => $this->backgroundUrl(),
        ]);
    }
};
?>

<div class="flex flex-col">
    <div class="relative h-[16rem] overflow-hidden">
        @if ($movie->imdb_id)
            <div class="absolute top-4 right-4 z-10">
                <flux:tooltip content="View on IMDb">
                    <a
                        href="{{ $this->imdbUrl() }}"
                        target="_blank"
                        class="flex items-center justify-center rounded-lg bg-white/10 p-2 text-white backdrop-blur-sm transition hover:bg-white/20"
                    >
                        <flux:icon.imdb class="size-8" />
                    </a>
                </flux:tooltip>
            </div>
        @endif

        <div class="relative flex h-full flex-col gap-4 px-4 py-5 sm:px-6 sm:py-6">
            <div class="max-w-4xl">
                <x-artwork
                    :model="$movie"
                    type="logo"
                    :alt="$movie->title . ' logo'"
                    class="h-12 drop-shadow sm:h-14 md:h-20"
                >
                    <flux:heading size="xl">{{ $movie->title }}</flux:heading>
                </x-artwork>
            </div>

            <div class="flex flex-wrap items-center gap-2 text-sm text-zinc-300">
                @if ($this->releaseDate())
                    <span>{{ $this->releaseDate() }}</span>
                @endif

                @if ($movie->status)
                    <flux:tooltip :content="$movie->status->getLabel()">
                        <x-dynamic-component
                            :component="'flux::icon.' . $movie->status->icon()"
                            variant="mini"
                            :class="$movie->status->iconColorClass()"
                        />
                    </flux:tooltip>
                @endif

                @if ($this->contentRating())
                    <flux:icon.dot variant="micro" class="text-zinc-300" />
                    <span>{{ $this->contentRating() }}</span>
                @endif

                @if ($this->formattedRuntime())
                    <flux:icon.dot variant="micro" class="text-zinc-300" />
                    <span>{{ $this->formattedRuntime() }}</span>
                @endif

                @if ($this->formattedLanguage())
                    <flux:icon.dot variant="micro" class="text-zinc-300" />
                    <span>{{ $this->formattedLanguage() }}</span>
                @endif
            </div>

            @if ($movie->genres && count($movie->genres))
                <div class="flex flex-wrap gap-2">
                    @foreach ($movie->genres as $genre)
                        <x-genre-badge :$genre />
                    @endforeach
                </div>
            @endif

            @if (count($this->productionCompanyNames()) > 0)
                <div class="text-xs text-zinc-400">
                    {{ implode(' · ', $this->productionCompanyNames()) }}
                </div>
            @endif

            @if ($this->titleVariants())
                <div class="text-xs text-zinc-400">{{ $this->titleVariants() }}</div>
            @endif
        </div>
    </div>

    <div class="flex flex-col gap-8 px-4 sm:px-6">
        <div class="flex gap-3">
            <livewire:cart.add-movie-button :movie="$movie" />
        </div>

        @if ($movie->imdb_id)
            <livewire:movies.plex-availability :movie="$movie" lazy />
        @endif
    </div>
</div>
