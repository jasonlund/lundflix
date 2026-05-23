<?php

declare(strict_types=1);

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

    public function getFluxColor(): string
    {
        return match ($this) {
            self::Pending => 'zinc',
            self::Fulfilled => 'green',
            self::Rejected => 'red',
            self::NotFound => 'amber',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Pending => 'clock',
            self::Fulfilled => 'check-circle',
            self::Rejected => 'x-circle',
            self::NotFound => 'question-mark-circle',
        };
    }

    public function getIconColorClass(): string
    {
        return match ($this) {
            self::Pending => 'text-zinc-400',
            self::Fulfilled => 'text-green-400',
            self::Rejected => 'text-red-400',
            self::NotFound => 'text-amber-400',
        };
    }
}
