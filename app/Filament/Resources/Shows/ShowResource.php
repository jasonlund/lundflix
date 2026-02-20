<?php

namespace App\Filament\Resources\Shows;

use App\Filament\Resources\Shows\Pages\ListShows;
use App\Filament\Resources\Shows\Pages\ViewShow;
use App\Filament\Resources\Shows\RelationManagers\EpisodesRelationManager;
use App\Filament\Resources\Shows\RelationManagers\SeasonArtworkRelationManager;
use App\Filament\Resources\Shows\RelationManagers\ShowArtworkRelationManager;
use App\Filament\Resources\Shows\Schemas\ShowInfolist;
use App\Filament\Resources\Shows\Tables\ShowsTable;
use App\Models\Show;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ShowResource extends Resource
{
    protected static ?string $model = Show::class;

    protected static string|BackedEnum|null $navigationIcon = 'lucide-tv';

    public static function infolist(Schema $schema): Schema
    {
        return ShowInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ShowsTable::configure($table);
    }

    /**
     * @return array<class-string<\Filament\Resources\RelationManagers\RelationManager>>
     */
    public static function getRelations(): array
    {
        return [
            EpisodesRelationManager::class,
            ShowArtworkRelationManager::class,
            SeasonArtworkRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShows::route('/'),
            'view' => ViewShow::route('/{record}'),
        ];
    }
}
