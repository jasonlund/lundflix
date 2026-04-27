<?php

declare(strict_types=1);

namespace App\Filament\Resources\SlackMessages;

use App\Filament\Resources\SlackMessages\Pages\ListSlackMessages;
use App\Filament\Resources\SlackMessages\Tables\SlackMessagesTable;
use App\Models\SlackMessage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;

class SlackMessageResource extends Resource
{
    protected static ?string $model = SlackMessage::class;

    protected static string|BackedEnum|null $navigationIcon = 'lucide-message-square';

    protected static ?string $navigationLabel = 'Slack Messages';

    public static function table(Table $table): Table
    {
        return SlackMessagesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSlackMessages::route('/'),
        ];
    }
}
