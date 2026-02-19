<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserRole;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('plex_thumb')
                    ->label('Avatar')
                    ->circular()
                    ->grow(false),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('plex_username')
                    ->label('Plex Username')
                    ->searchable()
                    ->sortable(),
                SelectColumn::make('role')
                    ->options(UserRole::class)
                    ->selectablePlaceholder(false)
                    ->disabled(fn ($record): bool => $record->id === Auth::id()),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->options(UserRole::class),
            ])
            ->defaultSort('name')
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
