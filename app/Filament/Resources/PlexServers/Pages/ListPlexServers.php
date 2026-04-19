<?php

namespace App\Filament\Resources\PlexServers\Pages;

use App\Filament\Resources\PlexServers\PlexServerResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Artisan;

class ListPlexServers extends ListRecords
{
    protected static string $resource = PlexServerResource::class;

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync')
                ->label('Sync Servers')
                ->icon('lucide-refresh-cw')
                ->action(function (): void {
                    Artisan::call('plex:sync-servers');

                    $this->sendSuccessNotification();
                }),
        ];
    }

    protected function sendSuccessNotification(): void
    {
        $this->getSavedNotification()?->send();
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Servers synced successfully');
    }
}
