<?php

namespace App\Filament\Tables;

use App\Actions\Media\ActivateMedia;
use App\Enums\MovieArtwork;
use App\Enums\TvArtwork;
use App\Models\Media;
use Filament\Actions\Action;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MediaTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('lucide-circle-check')
                    ->falseIcon('lucide-circle-minus')
                    ->trueColor('success')
                    ->falseColor('gray'),
                IconColumn::make('path')
                    ->label('Stored')
                    ->icon(fn (?string $state): string => $state
                        ? 'lucide-circle-check'
                        : 'lucide-circle-minus')
                    ->color(fn (?string $state): string => $state ? 'success' : 'gray'),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (Media $record): string => $record->getTypeLabel())
                    ->color(fn (Media $record): string => $record->getArtwork()?->getColor() ?? 'primary'),
                TextColumn::make('url')
                    ->label('URL')
                    ->limit(50)
                    ->tooltip(fn (Media $record): string => $record->url)
                    ->copyable()
                    ->copyMessage('URL copied'),
                TextColumn::make('lang')
                    ->label('Language')
                    ->placeholder('—')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('likes')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('season')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(fn ($livewire) => $livewire->getOwnerRecord()
                        ->media()
                        ->distinct()
                        ->orderBy('type')
                        ->pluck('type', 'type')
                        ->mapWithKeys(fn ($type) => [
                            $type => TvArtwork::tryFrom($type)?->getLabel()
                                ?? MovieArtwork::tryFrom($type)?->getLabel()
                                ?? $type,
                        ])
                        ->toArray()
                    ),
                SelectFilter::make('lang')
                    ->label('Language')
                    ->options(fn ($livewire) => $livewire->getOwnerRecord()
                        ->media()
                        ->whereNotNull('lang')
                        ->distinct()
                        ->orderBy('lang')
                        ->pluck('lang', 'lang')
                        ->toArray()
                    ),
            ])
            ->defaultSort('likes', 'desc')
            ->recordActions([
                Action::make('setActive')
                    ->label(fn (Media $record): string => $record->is_active ? 'Active' : 'Set Active')
                    ->icon(fn (Media $record) => $record->is_active ? 'lucide-circle-check' : 'lucide-circle-check')
                    ->color(fn (Media $record): string => $record->is_active ? 'success' : 'gray')
                    ->action(fn (Media $record) => app(ActivateMedia::class)->activate($record))
                    ->disabled(fn (Media $record): bool => $record->is_active),
                Action::make('preview')
                    ->label('Preview')
                    ->color('gray')
                    ->icon('lucide-eye')
                    ->modalHeading(fn (Media $record): string => $record->getTypeLabel())
                    ->schema([
                        Section::make()
                            ->schema([
                                ImageEntry::make('url')
                                    ->hiddenLabel()
                                    ->extraImgAttributes([
                                        'class' => 'max-h-[70vh] w-auto mx-auto',
                                        'loading' => 'lazy',
                                    ]),
                                TextEntry::make('fanart_id')
                                    ->label('FanArt ID')
                                    ->copyable(),
                                TextEntry::make('likes')
                                    ->icon('lucide-heart'),
                            ]),
                    ])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ]);
    }
}
