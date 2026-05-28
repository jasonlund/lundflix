<?php

declare(strict_types=1);

namespace App\Filament\Resources\Movies\Pages;

use App\Filament\Resources\Movies\MovieResource;
use App\Models\Movie;
use Filament\Actions\Action;
use Filament\Forms\Components\TagsInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewMovie extends ViewRecord
{
    protected static string $resource = MovieResource::class;

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('editIptSearchTerms')
                ->label('Edit IPT Search Terms')
                ->icon('lucide-search')
                ->fillForm(fn (Movie $record): array => [
                    'ipt_search_terms' => $record->ipt_search_terms ?? [],
                ])
                ->schema([
                    TagsInput::make('ipt_search_terms')
                        ->label('IPT Search Terms')
                        ->helperText('Override torrent search terms for this movie. Multiple terms are OR-combined in a single search; order is preserved.')
                        ->reorderable()
                        ->trim(),
                ])
                ->action(function (array $data, Movie $record): void {
                    $terms = array_values(array_filter(
                        $data['ipt_search_terms'] ?? [],
                        static fn ($t): bool => is_string($t) && trim($t) !== '',
                    ));

                    $record->update(['ipt_search_terms' => $terms === [] ? null : $terms]);

                    Notification::make()
                        ->success()
                        ->title('Search terms updated')
                        ->send();
                }),
        ];
    }
}
