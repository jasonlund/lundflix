<?php

namespace App\Filament\Resources\Requests\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Request Details')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('id'),
                        TextEntry::make('user.name')
                            ->label('User'),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'pending' => 'warning',
                                'fulfilled' => 'success',
                                'rejected' => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('created_at')
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->dateTime(),
                    ]),
                Section::make('Notes')
                    ->schema([
                        TextEntry::make('notes')
                            ->columnSpanFull()
                            ->placeholder('No notes'),
                    ]),
            ]);
    }
}
