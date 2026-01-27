<?php

namespace App\Filament\Resources\Shows\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ShowsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable(),
                TextColumn::make('tvmaze_id')
                    ->label('TVMaze ID')
                    ->sortable(),
                TextColumn::make('imdb_id')
                    ->label('IMDb ID')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Running' => 'success',
                        'Ended' => 'danger',
                        'To Be Determined' => 'warning',
                        default => 'gray',
                    }),
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
