<?php

namespace App\Filament\Resources\Movies\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MoviesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable(),
                TextColumn::make('imdb_id')
                    ->label('IMDb ID')
                    ->searchable(),
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('year')
                    ->sortable(),
                TextColumn::make('runtime')
                    ->suffix(' min')
                    ->sortable(),
                TextColumn::make('genres'),
                TextColumn::make('num_votes')
                    ->label('Votes')
                    ->numeric()
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
