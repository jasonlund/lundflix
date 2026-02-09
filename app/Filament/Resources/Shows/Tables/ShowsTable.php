<?php

namespace App\Filament\Resources\Shows\Tables;

use App\Models\Show;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ShowsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->searchUsing(fn (Builder $query, string $search): Builder => $query->whereKey(Show::search($search)->keys()))
            ->searchable()
            ->columns([
                TextColumn::make('id')
                    ->sortable(),
                TextColumn::make('tvmaze_id')
                    ->label('TVMaze ID')
                    ->sortable(),
                TextColumn::make('imdb_id')
                    ->label('IMDb ID'),
                TextColumn::make('name')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('premiered')
                    ->date()
                    ->sortable(),
                TextColumn::make('ended')
                    ->date()
                    ->sortable(),
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
