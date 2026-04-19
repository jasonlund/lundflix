<?php

namespace App\Filament\RelationManagers;

use App\Actions\TMDB\UpsertTMDBImages;
use App\Filament\Tables\MediaTable;
use App\Models\Movie;
use App\Models\Show;
use App\Services\ThirdParty\TMDBService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class MediaRelationManager extends RelationManager
{
    protected static string $relationship = 'media';

    protected static ?string $title = 'Artwork';

    public function table(Table $table): Table
    {
        return MediaTable::configure($table)
            ->headerActions([
                Action::make('syncArtwork')
                    ->label(fn (): string => $this->getMediableOwner()->media()->exists()
                        ? 'Refresh Artwork'
                        : 'Fetch Artwork')
                    ->icon('lucide-refresh-cw')
                    ->action(function (Action $action, TMDBService $tmdb): void {
                        $owner = $this->getMediableOwner();

                        if (! $owner->tmdb_id) {
                            Notification::make()
                                ->title('Missing TMDB ID')
                                ->body('This title has no TMDB ID configured.')
                                ->danger()
                                ->send();

                            $action->halt();
                        }

                        $details = match (true) {
                            $owner instanceof Movie => $tmdb->movieDetails($owner->tmdb_id),
                            $owner instanceof Show => $tmdb->showDetails($owner->tmdb_id),
                        };

                        if (! $details || ! isset($details['images'])) {
                            Notification::make()
                                ->title('No artwork found')
                                ->body('TMDB has no artwork for this title.')
                                ->warning()
                                ->send();

                            $action->halt();
                        }

                        app(UpsertTMDBImages::class)->upsert($owner, $details['images']);

                        $this->dispatch('$refresh')->self();

                        $count = $owner->media()->count();

                        Notification::make()
                            ->title('Artwork synced')
                            ->body("{$count} artwork items available.")
                            ->success()
                            ->send();
                    }),
            ]);
    }

    private function getMediableOwner(): Movie|Show
    {
        $owner = $this->getOwnerRecord();
        assert($owner instanceof Movie || $owner instanceof Show);

        return $owner;
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
