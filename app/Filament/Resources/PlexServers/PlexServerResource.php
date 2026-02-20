<?php

namespace App\Filament\Resources\PlexServers;

use App\Filament\Resources\PlexServers\Pages\ListPlexServers;
use App\Filament\Resources\PlexServers\Tables\PlexServersTable;
use App\Models\PlexMediaServer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;

class PlexServerResource extends Resource
{
    protected static ?string $model = PlexMediaServer::class;

    protected static string|BackedEnum|null $navigationIcon = 'lucide-server';

    protected static ?string $navigationLabel = 'Plex Servers';

    protected static ?string $modelLabel = 'Plex Server';

    protected static ?string $pluralModelLabel = 'Plex Servers';

    protected static ?string $slug = 'plex-servers';

    public static function table(Table $table): Table
    {
        return PlexServersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlexServers::route('/'),
        ];
    }
}
