<?php

declare(strict_types=1);

namespace App\Filament\Resources\SlackMessages\Tables;

use App\Enums\SlackNotificationType;
use App\Models\SlackMessage;
use App\Services\SlackService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SlackMessagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->badge(),
                TextColumn::make('content')
                    ->formatStateUsing(fn (string $state): string => self::formatSlackContent($state))
                    ->html()
                    ->wrap(),
                TextColumn::make('sent_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(SlackNotificationType::class),
            ])
            ->defaultSort('sent_at', 'desc')
            ->recordActions([
                Action::make('edit')
                    ->icon('lucide-pencil')
                    ->modalHeading('Edit Slack Message')
                    ->fillForm(fn (SlackMessage $record): array => [
                        'content' => $record->content,
                    ])
                    ->schema([
                        Textarea::make('content')
                            ->required()
                            ->rows(6),
                    ])
                    ->action(function (array $data, SlackMessage $record): void {
                        try {
                            app(SlackService::class)->updateMessage($record, $data['content']);

                            Notification::make()
                                ->title('Message updated')
                                ->success()
                                ->send();
                        } catch (\RuntimeException $e) {
                            Notification::make()
                                ->title('Failed to update message')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('delete')
                    ->color('danger')
                    ->icon('lucide-trash-2')
                    ->modalHeading('Delete Slack Message')
                    ->requiresConfirmation()
                    ->action(function (SlackMessage $record): void {
                        try {
                            app(SlackService::class)->deleteMessage($record);

                            Notification::make()
                                ->title('Message deleted')
                                ->success()
                                ->send();
                        } catch (\RuntimeException $e) {
                            Notification::make()
                                ->title('Failed to delete message')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ]);
    }

    private static function formatSlackContent(string $content): string
    {
        $anchors = [];

        $content = preg_replace_callback(
            '/<((?:https?:\/\/|mailto:)[^>|]+)\|([^>]+)>/',
            function (array $matches) use (&$anchors): string {
                $placeholder = '__SLACK_LINK_'.count($anchors).'__';
                $anchors[$placeholder] = sprintf(
                    '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
                    e($matches[1]),
                    e($matches[2]),
                );

                return $placeholder;
            },
            $content,
        ) ?? $content;

        $content = e($content);
        $content = preg_replace('/\*([^*]+)\*/', '<strong>$1</strong>', $content) ?? $content;
        $content = str_replace(array_keys($anchors), array_values($anchors), $content);

        return nl2br($content);
    }
}
