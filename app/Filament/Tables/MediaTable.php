<?php

namespace App\Filament\Tables;

use App\Models\Media;
use Filament\Actions\Action;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class MediaTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Str::headline($state))
                    ->color(fn (string $state): string => match ($state) {
                        'hdmovielogo', 'hdtvlogo', 'hdclearlogo' => 'info',
                        'movieposter', 'tvposter' => 'success',
                        'moviebackground', 'showbackground' => 'warning',
                        'moviedisc', 'cdart' => 'gray',
                        default => 'primary',
                    }),
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
                        ->mapWithKeys(fn ($type) => [$type => Str::headline($type)])
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
                Action::make('preview')
                    ->label('Preview')
                    ->color('gray')
                    ->icon(Heroicon::Eye)
                    ->modalHeading(fn (Media $record): string => Str::headline($record->type))
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
                                    ->icon(Heroicon::Heart),
                            ]),
                    ])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ]);
    }
}
