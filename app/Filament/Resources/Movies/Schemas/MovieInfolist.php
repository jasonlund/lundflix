<?php

namespace App\Filament\Resources\Movies\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MovieInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Movie Details')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('id'),
                        TextEntry::make('imdb_id')
                            ->label('IMDb ID')
                            ->url(fn ($record) => "https://www.imdb.com/title/{$record->imdb_id}/")
                            ->openUrlInNewTab(),
                        TextEntry::make('title'),
                        TextEntry::make('year'),
                        TextEntry::make('runtime')
                            ->suffix(' minutes'),
                        TextEntry::make('genres'),
                        TextEntry::make('num_votes')
                            ->label('Votes')
                            ->numeric(),
                        TextEntry::make('created_at')
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->dateTime(),
                    ]),
            ]);
    }
}
