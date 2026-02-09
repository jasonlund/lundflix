<?php

namespace App\Filament\Resources\Requests\Pages;

use App\Filament\Resources\Requests\RequestResource;
use App\Filament\Resources\Requests\Widgets\IptSearchLinksWidget;
use Filament\Resources\Pages\ViewRecord;

class ViewRequest extends ViewRecord
{
    protected static string $resource = RequestResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            IptSearchLinksWidget::class,
        ];
    }
}
