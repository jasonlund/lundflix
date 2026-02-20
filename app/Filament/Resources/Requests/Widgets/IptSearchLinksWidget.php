<?php

namespace App\Filament\Resources\Requests\Widgets;

use App\Enums\IptCategory;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\RequestItem;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;

class IptSearchLinksWidget extends TableWidget
{
    protected string $view = 'filament.resources.requests.widgets.ipt-search-links-widget';

    protected int|string|array $columnSpan = 'full';

    public \App\Models\Request $record;

    /** @var Collection<int, string>|null */
    private ?Collection $completeSeasonKeys = null;

    public function table(Table $table): Table
    {
        $completeKeys = $this->getCompleteSeasonKeys();
        $excludeIds = $this->getExcludeIds($completeKeys);

        return $table
            ->query(
                RequestItem::query()
                    ->where('request_id', $this->record->id)
                    ->whereNotIn('id', $excludeIds)
                    ->with(['requestable' => function (MorphTo $morphTo): void { // @phpstan-ignore argument.type
                        $morphTo->morphWith([Episode::class => ['show']]);
                    }])
            )
            ->columns([
                TextColumn::make('show_name')
                    ->label('Title')
                    ->getStateUsing(function (RequestItem $record): string {
                        $requestable = $record->requestable;

                        if ($requestable instanceof Movie) {
                            return "{$requestable->title} ({$requestable->year})";
                        }

                        /** @var Episode $requestable */
                        return $requestable->show->name;
                    }),
            ])
            ->recordActions([
                Action::make('search')
                    ->label('Search')
                    ->icon('lucide-external-link')
                    ->url(fn (RequestItem $record): string => $this->buildSearchUrl($record, $this->getQueryForItem($record)))
                    ->openUrlInNewTab(),
                Action::make('buildLink')
                    ->label('Build Link')
                    ->icon('lucide-wrench')
                    ->color('gray')
                    ->modalHeading('IPT Link Builder')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(fn (RequestItem $record): View => view(
                        'filament.resources.requests.widgets.ipt-link-builder',
                        [
                            'query' => $this->getQueryForItem($record),
                            'categories' => $record->requestable instanceof Movie
                                ? IptCategory::options(IptCategory::movieCases())
                                : IptCategory::options(IptCategory::tvCases()),
                            'defaults' => $this->getDefaultCategoryValues($record),
                            'suggestions' => $this->getSuggestions($record),
                        ],
                    )),
            ])
            ->heading('IPT Search Links')
            ->paginated(false);
    }

    public function hasSearchableItems(): bool
    {
        return RequestItem::query()
            ->where('request_id', $this->record->id)
            ->whereIn('requestable_type', [Episode::class, Movie::class])
            ->exists();
    }

    /**
     * Get the keys of show+season groups that represent complete seasons.
     *
     * @return Collection<int, string>
     */
    private function getCompleteSeasonKeys(): Collection
    {
        if ($this->completeSeasonKeys !== null) {
            return $this->completeSeasonKeys;
        }

        /** @var Collection<int, RequestItem> $episodeItems */
        $episodeItems = RequestItem::query()
            ->where('request_id', $this->record->id)
            ->where('requestable_type', Episode::class)
            ->with('requestable')
            ->get();

        if ($episodeItems->isEmpty()) {
            return $this->completeSeasonKeys = collect();
        }

        /** @var Collection<string, Collection<int, RequestItem>> $grouped */
        $grouped = $episodeItems->groupBy(function (RequestItem $item): string {
            /** @var Episode $episode */
            $episode = $item->requestable;

            return $episode->show_id.'-'.$episode->season;
        });

        $this->completeSeasonKeys = $grouped
            ->filter(function (Collection $items, string $key): bool {
                /** @var Episode $firstEpisode */
                $firstEpisode = $items->first()->requestable;

                return $this->isCompleteSeason($firstEpisode->show_id, $firstEpisode->season, $items->count());
            })
            ->keys();

        return $this->completeSeasonKeys;
    }

    /**
     * Get IDs that should be excluded from the table (duplicates for complete seasons).
     *
     * @param  Collection<int, string>  $completeKeys
     * @return list<int>
     */
    private function getExcludeIds(Collection $completeKeys): array
    {
        if ($completeKeys->isEmpty()) {
            return [];
        }

        /** @var Collection<int, RequestItem> $episodeItems */
        $episodeItems = RequestItem::query()
            ->where('request_id', $this->record->id)
            ->where('requestable_type', Episode::class)
            ->with('requestable')
            ->get();

        /** @var Collection<string, Collection<int, RequestItem>> $grouped */
        $grouped = $episodeItems->groupBy(function (RequestItem $item): string {
            /** @var Episode $episode */
            $episode = $item->requestable;

            return $episode->show_id.'-'.$episode->season;
        });

        $excludeIds = [];

        foreach ($grouped as $key => $items) {
            if (! $completeKeys->contains($key)) {
                continue;
            }

            // Keep the first item, exclude the rest
            $items->shift();
            foreach ($items as $item) {
                $excludeIds[] = $item->id;
            }
        }

        return $excludeIds;
    }

    /**
     * Get the search query string for a request item.
     */
    private function getQueryForItem(RequestItem $record): string
    {
        $requestable = $record->requestable;

        if ($requestable instanceof Movie) {
            return $requestable->imdb_id;
        }

        /** @var Episode $requestable */
        $show = $requestable->show;
        $key = $requestable->show_id.'-'.$requestable->season;

        if ($this->getCompleteSeasonKeys()->contains($key)) {
            $seasonCode = 'S'.str_pad((string) $requestable->season, 2, '0', STR_PAD_LEFT);

            return "{$show->imdb_id} {$seasonCode}";
        }

        return "{$show->imdb_id} {$requestable->code}";
    }

    /**
     * Check if all episodes in a season are requested and the season is not currently running.
     */
    private function isCompleteSeason(int $showId, int $season, int $requestedCount): bool
    {
        $totalInSeason = Episode::where('show_id', $showId)
            ->where('season', $season)
            ->count();

        if ($requestedCount < $totalInSeason) {
            return false;
        }

        $hasUnaired = Episode::where('show_id', $showId)
            ->where('season', $season)
            ->where(function ($query): void {
                $query->where('airdate', '>', now()->startOfDay())
                    ->orWhereNull('airdate');
            })
            ->exists();

        return ! $hasUnaired;
    }

    /** @return list<int> */
    private function getDefaultCategoryValues(RequestItem $record): array
    {
        return $record->requestable instanceof Movie
            ? IptCategory::defaultMovieValues()
            : IptCategory::defaultTvValues();
    }

    /** @return list<string> */
    private function getSuggestions(RequestItem $record): array
    {
        $requestable = $record->requestable;

        if ($requestable instanceof Movie) {
            return [
                $requestable->title,
                (string) $requestable->year,
                "{$requestable->title} {$requestable->year}",
                $requestable->imdb_id,
            ];
        }

        /** @var Episode $requestable */
        $show = $requestable->show;
        $seasonCode = 'S'.str_pad((string) $requestable->season, 2, '0', STR_PAD_LEFT);

        $suggestions = [
            $show->name,
            $show->imdb_id,
            $seasonCode,
        ];

        $key = $requestable->show_id.'-'.$requestable->season;
        if (! $this->getCompleteSeasonKeys()->contains($key)) {
            $suggestions[] = $requestable->code;
        }

        return $suggestions;
    }

    private function buildSearchUrl(RequestItem $record, string $query): string
    {
        $categories = $record->requestable instanceof Movie
            ? IptCategory::queryString([IptCategory::MovieX265])
            : IptCategory::queryString([IptCategory::TvPacks, IptCategory::TvX265]);

        return "https://iptorrents.com/t?{$categories}&q=".urlencode($query).'&qf=#torrents';
    }
}
