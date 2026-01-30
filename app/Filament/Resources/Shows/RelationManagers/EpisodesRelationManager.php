<?php

namespace App\Filament\Resources\Shows\RelationManagers;

use App\Filament\Resources\Shows\Tables\EpisodesTable;
use App\Jobs\StoreShowEpisodes;
use App\Models\Show;
use App\Services\TVMazeService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Http\Client\RequestException;

class EpisodesRelationManager extends RelationManager
{
    protected static string $relationship = 'episodes';

    public function table(Table $table): Table
    {
        return EpisodesTable::configure($table)
            ->headerActions([
                Action::make('fetchEpisodes')
                    ->label(fn () => $this->getShow()->episodes()->exists()
                        ? 'Refresh Episodes'
                        : 'Fetch Episodes')
                    ->icon(Heroicon::ArrowPathRoundedSquare)
                    ->action(function (TVMazeService $tvMaze): void {
                        $show = $this->getShow();

                        try {
                            $episodes = $tvMaze->episodes($show->tvmaze_id);
                        } catch (RequestException $e) {
                            Notification::make()
                                ->title('Failed to fetch episodes')
                                ->body('Could not connect to TVMaze API.')
                                ->danger()
                                ->send();

                            return;
                        }

                        if (empty($episodes)) {
                            Notification::make()
                                ->title('No episodes found')
                                ->body('TVMaze has no episode data for this show.')
                                ->warning()
                                ->send();

                            return;
                        }

                        StoreShowEpisodes::dispatchSync($show, $episodes);

                        $this->dispatch('$refresh')->self();

                        Notification::make()
                            ->title('Episodes imported')
                            ->body(count($episodes).' episodes have been imported.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    private function getShow(): Show
    {
        $show = $this->getOwnerRecord();
        assert($show instanceof Show);

        return $show;
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
