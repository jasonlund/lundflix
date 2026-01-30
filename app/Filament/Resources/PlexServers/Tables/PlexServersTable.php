<?php

namespace App\Filament\Resources\PlexServers\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class PlexServersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_online')
                    ->label('Online')
                    ->boolean(),
                ToggleColumn::make('visible'),
                IconColumn::make('owned')
                    ->boolean(),
                TextColumn::make('uri')
                    ->label('URI')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('client_identifier')
                    ->label('Client ID')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_seen_at')
                    ->label('Last Seen')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('name');
    }
}
