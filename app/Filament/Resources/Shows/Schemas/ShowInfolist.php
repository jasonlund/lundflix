<?php

namespace App\Filament\Resources\Shows\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ShowInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Show Details')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('id'),
                        TextEntry::make('tvmaze_id')
                            ->label('TVMaze ID'),
                        TextEntry::make('imdb_id')
                            ->label('IMDb ID')
                            ->url(fn ($record) => $record->imdb_id ? "https://www.imdb.com/title/{$record->imdb_id}/" : null)
                            ->openUrlInNewTab(),
                        TextEntry::make('thetvdb_id')
                            ->label('TheTVDB ID'),
                        TextEntry::make('name'),
                        TextEntry::make('type'),
                        TextEntry::make('language'),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'Running' => 'success',
                                'Ended' => 'danger',
                                'To Be Determined' => 'warning',
                                default => 'gray',
                            }),
                        TextEntry::make('runtime')
                            ->suffix(' minutes'),
                        TextEntry::make('premiered')
                            ->date(),
                        TextEntry::make('ended')
                            ->date(),
                        TextEntry::make('genres')
                            ->badge()
                            ->separator(','),
                        TextEntry::make('num_votes')
                            ->label('Votes')
                            ->numeric(),
                    ]),
                Section::make('Metadata')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        TextEntry::make('schedule')
                            ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT) : $state)
                            ->columnSpanFull(),
                        TextEntry::make('network')
                            ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT) : $state),
                        TextEntry::make('web_channel')
                            ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT) : $state),
                        TextEntry::make('created_at')
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->dateTime(),
                    ]),
            ]);
    }
}
