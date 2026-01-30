<?php

namespace App\Filament\RelationManagers;

use App\Filament\Tables\MediaTable;
use App\Jobs\StoreFanart;
use App\Models\Movie;
use App\Models\Show;
use App\Services\FanartTVService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Http\Client\RequestException;

class MediaRelationManager extends RelationManager
{
    protected static string $relationship = 'media';

    protected static ?string $title = 'Artwork';

    public function table(Table $table): Table
    {
        return MediaTable::configure($table)
            ->headerActions([
                Action::make('syncArtwork')
                    ->label(fn () => $this->getMediableOwner()->media()->exists()
                        ? 'Refresh Artwork'
                        : 'Fetch Artwork')
                    ->icon(Heroicon::ArrowPathRoundedSquare)
                    ->action(function (FanartTVService $fanart): void {
                        $owner = $this->getMediableOwner();

                        if ($owner instanceof Movie && ! $owner->imdb_id) {
                            Notification::make()
                                ->title('Missing IMDB ID')
                                ->body('This movie has no IMDB ID configured.')
                                ->danger()
                                ->send();

                            return;
                        }

                        if ($owner instanceof Show && ! $owner->thetvdb_id) {
                            Notification::make()
                                ->title('Missing TVDB ID')
                                ->body('This show has no TVDB ID configured.')
                                ->danger()
                                ->send();

                            return;
                        }

                        try {
                            $response = match (true) {
                                $owner instanceof Movie => $fanart->movie($owner->imdb_id),
                                $owner instanceof Show => $fanart->show($owner->thetvdb_id),
                            };
                        } catch (RequestException $e) {
                            Notification::make()
                                ->title('Failed to fetch artwork')
                                ->body('Could not connect to FanArt API.')
                                ->danger()
                                ->send();

                            return;
                        }

                        if ($response === null) {
                            Notification::make()
                                ->title('No artwork found')
                                ->body('FanArt has no artwork for this title.')
                                ->warning()
                                ->send();

                            return;
                        }

                        StoreFanart::dispatchSync($owner, $response);

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
