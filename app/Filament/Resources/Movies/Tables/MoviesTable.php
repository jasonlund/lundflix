<?php

namespace App\Filament\Resources\Movies\Tables;

use App\Models\Movie;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MoviesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->searchUsing(fn (Builder $query, string $search): Builder => $query->whereKey(Movie::search($search)->keys()))
            ->searchable()
            ->columns([
                TextColumn::make('id')
                    ->sortable(),
                TextColumn::make('imdb_id')
                    ->label('IMDb ID'),
                TextColumn::make('title')
                    ->sortable(),
                TextColumn::make('year')
                    ->sortable(),
                TextColumn::make('runtime')
                    ->suffix(' min')
                    ->sortable(),
                TextColumn::make('genres')
                    ->badge()
                    ->separator(','),
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
