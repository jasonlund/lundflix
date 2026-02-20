<?php

namespace App\Filament\Resources\Requests;

use App\Filament\Resources\Requests\Pages\ListRequests;
use App\Filament\Resources\Requests\Pages\ViewRequest;
use App\Filament\Resources\Requests\RelationManagers\RequestItemsRelationManager;
use App\Filament\Resources\Requests\Schemas\RequestInfolist;
use App\Filament\Resources\Requests\Tables\RequestsTable;
use App\Filament\Resources\Requests\Widgets\IptSearchLinksWidget;
use App\Models\Request;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class RequestResource extends Resource
{
    protected static ?string $model = Request::class;

    protected static string|BackedEnum|null $navigationIcon = 'lucide-inbox';

    public static function infolist(Schema $schema): Schema
    {
        return RequestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RequestsTable::configure($table);
    }

    /**
     * @return array<class-string<\Filament\Resources\RelationManagers\RelationManager>>
     */
    public static function getRelations(): array
    {
        return [
            RequestItemsRelationManager::class,
        ];
    }

    public static function getWidgets(): array
    {
        return [
            IptSearchLinksWidget::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRequests::route('/'),
            'view' => ViewRequest::route('/{record}'),
        ];
    }
}
