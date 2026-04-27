<?php

declare(strict_types=1);

namespace App\Filament\Resources\SlackMessages\Pages;

use App\Filament\Resources\SlackMessages\SlackMessageResource;
use Filament\Resources\Pages\ListRecords;

class ListSlackMessages extends ListRecords
{
    protected static string $resource = SlackMessageResource::class;
}
