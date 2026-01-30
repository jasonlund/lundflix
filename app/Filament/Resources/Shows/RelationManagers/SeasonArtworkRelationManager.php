<?php

namespace App\Filament\Resources\Shows\RelationManagers;

use App\Enums\TvArtwork;
use App\Enums\TvArtworkLevel;
use App\Filament\Tables\MediaTable;
use App\Models\Show;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SeasonArtworkRelationManager extends RelationManager
{
    protected static string $relationship = 'media';

    protected static ?string $title = 'Season Artwork';

    public function table(Table $table): Table
    {
        return MediaTable::configure($table)
            ->modifyQueryUsing(fn (Builder $query) => $query->whereIn(
                'type',
                TvArtwork::valuesForLevel(TvArtworkLevel::Season)
            ))
            ->filters([
                SelectFilter::make('season')
                    ->options(fn () => $this->getSeasonOptions()),
                ...MediaTable::configure($table)->getFilters(),
            ]);
    }

    /**
     * @return array<int|string, string>
     */
    private function getSeasonOptions(): array
    {
        $show = $this->getShow();

        // Get distinct seasons from the media table for this show
        $seasons = $show->media()
            ->whereIn('type', TvArtwork::valuesForLevel(TvArtworkLevel::Season))
            ->distinct()
            ->orderBy('season')
            ->pluck('season')
            ->filter(fn ($season) => $season !== null);

        $options = [];

        foreach ($seasons as $season) {
            $options[$season] = $season === 0 ? 'All Seasons' : "Season {$season}";
        }

        return $options;
    }

    private function getShow(): Show
    {
        $owner = $this->getOwnerRecord();
        assert($owner instanceof Show);

        return $owner;
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
