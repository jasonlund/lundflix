<?php

namespace App\Filament\Resources\Requests\Tables;

use App\Enums\RequestItemStatus;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\RequestItem;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RequestItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('requestable_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Movie::class => 'Movie',
                        Episode::class => 'Episode',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        Movie::class => 'info',
                        Episode::class => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('requestable')
                    ->label('Item')
                    ->formatStateUsing(function (RequestItem $record): string {
                        $requestable = $record->requestable;

                        if ($requestable instanceof Movie) {
                            return "{$requestable->title} ({$requestable->year})";
                        }

                        if ($requestable instanceof Episode) {
                            return "{$requestable->show->name} - {$requestable->code}";
                        }

                        return 'Unknown';
                    }),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (RequestItemStatus $state): string => match ($state) {
                        RequestItemStatus::Pending => 'Pending',
                        RequestItemStatus::Fulfilled => 'Fulfilled',
                        RequestItemStatus::Rejected => 'Rejected',
                        RequestItemStatus::NotFound => 'Not Found',
                    })
                    ->color(fn (RequestItemStatus $state): string => match ($state) {
                        RequestItemStatus::Pending => 'gray',
                        RequestItemStatus::Fulfilled => 'success',
                        RequestItemStatus::Rejected => 'danger',
                        RequestItemStatus::NotFound => 'warning',
                    })
                    ->sortable(),
                TextColumn::make('actionedBy.name')
                    ->label('Actioned By')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('actioned_at')
                    ->label('Actioned At')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->checkIfRecordIsSelectableUsing(function (RequestItem $record): bool {
                $user = auth()->user();

                if (! $user) {
                    return false;
                }

                return $user->can('update', [$record, RequestItemStatus::Fulfilled]);
            })
            ->defaultSort('created_at', 'desc');
    }
}
