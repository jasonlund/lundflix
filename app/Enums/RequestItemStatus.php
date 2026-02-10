<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum RequestItemStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Fulfilled = 'fulfilled';
    case Rejected = 'rejected';
    case NotFound = 'not_found';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Fulfilled => 'Fulfilled',
            self::Rejected => 'Rejected',
            self::NotFound => 'Not Found',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Fulfilled => 'success',
            self::Rejected => 'danger',
            self::NotFound => 'warning',
        };
    }
}
