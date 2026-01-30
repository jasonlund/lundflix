<?php

namespace App\Filament\Resources\Shows\RelationManagers;

use App\Enums\TvArtwork;
use App\Enums\TvArtworkLevel;
use App\Filament\Tables\MediaTable;
use App\Jobs\StoreFanart;
use App\Models\Show;
use App\Services\FanartTVService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\RequestException;

class ShowArtworkRelationManager extends RelationManager
{
    protected static string $relationship = 'media';

    protected static ?string $title = 'Show Artwork';

    public function table(Table $table): Table
    {
        return MediaTable::configure($table)
            ->modifyQueryUsing(fn (Builder $query) => $query->whereIn(
                'type',
                TvArtwork::valuesForLevel(TvArtworkLevel::Show)
            ))
            ->headerActions([
                Action::make('syncArtwork')
                    ->label(fn () => $this->getShow()->media()->exists()
                        ? 'Refresh Artwork'
                        : 'Fetch Artwork')
                    ->icon(Heroicon::ArrowPathRoundedSquare)
                    ->action(function (FanartTVService $fanart): void {
                        $show = $this->getShow();

                        if (! $show->thetvdb_id) {
                            Notification::make()
                                ->title('Missing TVDB ID')
                                ->body('This show has no TVDB ID configured.')
                                ->danger()
                                ->send();

                            return;
                        }

                        try {
                            $response = $fanart->show($show->thetvdb_id);
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

                        StoreFanart::dispatchSync($show, $response);

                        $this->dispatch('$refresh')->self();

                        $count = $show->media()->count();

                        Notification::make()
                            ->title('Artwork synced')
                            ->body("{$count} artwork items available.")
                            ->success()
                            ->send();
                    }),
            ]);
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
