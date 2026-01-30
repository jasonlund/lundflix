<?php

namespace App\Filament\Resources\Movies;

use App\Filament\RelationManagers\MediaRelationManager;
use App\Filament\Resources\Movies\Pages\ListMovies;
use App\Filament\Resources\Movies\Pages\ViewMovie;
use App\Filament\Resources\Movies\Schemas\MovieInfolist;
use App\Filament\Resources\Movies\Tables\MoviesTable;
use App\Models\Movie;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MovieResource extends Resource
{
    protected static ?string $model = Movie::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFilm;

    public static function infolist(Schema $schema): Schema
    {
        return MovieInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MoviesTable::configure($table);
    }

    /**
     * @return array<class-string<RelationManager>>
     */
    public static function getRelations(): array
    {
        return [
            MediaRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMovies::route('/'),
            'view' => ViewMovie::route('/{record}'),
        ];
    }
}
