<?php

namespace App\Filament\Resources\Shows\RelationManagers;

use App\Filament\Resources\Shows\Tables\EpisodesTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class EpisodesRelationManager extends RelationManager
{
    protected static string $relationship = 'episodes';

    public function table(Table $table): Table
    {
        return EpisodesTable::configure($table);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
