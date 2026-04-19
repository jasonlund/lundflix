<?php

declare(strict_types=1);

namespace App\Filament\Tables;

use App\Actions\Media\ActivateMedia;
use App\Enums\ArtworkType;
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
                    ->badge(),
                TextColumn::make('file_path')
                    ->label('File Path')
                    ->limit(50)
                    ->tooltip(fn (Media $record): string => $record->file_path)
                    ->copyable()
                    ->copyMessage('File path copied'),
                TextColumn::make('lang')
                    ->label('Language')
                    ->placeholder('—')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('vote_average')
                    ->label('Rating')
                    ->numeric(2)
                    ->sortable(),
                TextColumn::make('season')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(ArtworkType::class),
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
            ->defaultSort('vote_average', 'desc')
            ->recordActions([
                Action::make('setActive')
                    ->label(fn (Media $record): string => $record->is_active ? 'Active' : 'Set Active')
                    ->icon(fn (Media $record): string => $record->is_active ? 'lucide-circle-check' : 'lucide-circle-minus')
                    ->color(fn (Media $record): string => $record->is_active ? 'success' : 'gray')
                    ->action(fn (Media $record) => app(ActivateMedia::class)->activate($record))
                    ->disabled(fn (Media $record): bool => $record->is_active),
                Action::make('preview')
                    ->label('Preview')
                    ->color('gray')
                    ->icon('lucide-eye')
                    ->modalHeading(fn (Media $record): string => $record->type->getLabel())
                    ->schema([
                        Section::make()
                            ->schema([
                                ImageEntry::make('file_path')
                                    ->hiddenLabel()
                                    ->state(fn (Media $record): string => $record->url())
                                    ->extraImgAttributes([
                                        'class' => 'max-h-[70vh] w-auto mx-auto',
                                        'loading' => 'lazy',
                                    ]),
                                TextEntry::make('file_path')
                                    ->label('TMDB Path')
                                    ->copyable(),
                                TextEntry::make('vote_average')
                                    ->label('Rating')
                                    ->icon('lucide-star'),
                            ]),
                    ])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ]);
    }
}
