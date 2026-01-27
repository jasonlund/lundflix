<?php

namespace App\Filament\Resources\Requests\RelationManagers;

use App\Filament\Resources\Requests\Tables\RequestItemsTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class RequestItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Request Items';

    public function table(Table $table): Table
    {
        return RequestItemsTable::configure($table);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
