<?php

namespace App\Filament\Resources\Shows\Tables;

use App\Enums\EpisodeType;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EpisodesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->sortable(['season', 'number']),
                TextColumn::make('name')
                    ->searchable()
                    ->limit(50),
                TextColumn::make('type')
                    ->badge(),
                TextColumn::make('airdate')
                    ->date()
                    ->sortable(),
                TextColumn::make('runtime')
                    ->suffix(' min')
                    ->sortable(),
                TextColumn::make('rating.average')
                    ->label('Rating')
                    ->numeric(1)
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('season')
                    ->options(fn ($livewire) => $livewire->getOwnerRecord()
                        ->episodes()
                        ->distinct()
                        ->orderBy('season')
                        ->pluck('season', 'season')
                        ->mapWithKeys(fn ($season) => [$season => "Season {$season}"])
                        ->toArray()
                    ),
                SelectFilter::make('type')
                    ->options(EpisodeType::class),
            ])
            // TODO: Fix multi-column sorting - chained defaultSort() calls override each other.
            // Use ->modifyQueryUsing(fn ($query) => $query->orderBy('season')->orderBy('number'))
            ->defaultSort('season')
            ->defaultSort('number');
    }
}
