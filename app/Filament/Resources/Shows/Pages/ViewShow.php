<?php

namespace App\Filament\Resources\Shows\Pages;

use App\Filament\Resources\Shows\RelationManagers\EpisodesRelationManager;
use App\Filament\Resources\Shows\ShowResource;
use App\Jobs\StoreShowEpisodes;
use App\Models\Show;
use App\Services\TVMazeService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Http\Client\RequestException;

class ViewShow extends ViewRecord
{
    protected static string $resource = ShowResource::class;

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('fetchEpisodes')
                ->label(function (): string {
                    /** @var Show $show */
                    $show = $this->record;

                    return $show->episodes()->exists()
                        ? 'Refresh Episodes'
                        : 'Fetch Episodes';
                })
                ->icon(Heroicon::ArrowPathRoundedSquare)
                ->authorize('fetchEpisodes')
                ->action(function (TVMazeService $tvMaze): void {
                    /** @var Show $show */
                    $show = $this->record;

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

                    if ($episodes === null || empty($episodes)) {
                        Notification::make()
                            ->title('No episodes found')
                            ->body('TVMaze has no episode data for this show.')
                            ->warning()
                            ->send();

                        return;
                    }

                    StoreShowEpisodes::dispatchSync($show, $episodes);

                    $this->dispatch('$refresh')->to(EpisodesRelationManager::class);

                    Notification::make()
                        ->title('Episodes imported')
                        ->body(count($episodes).' episodes have been imported.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
