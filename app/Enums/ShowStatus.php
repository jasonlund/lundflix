<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ShowStatus: string implements HasColor, HasIcon, HasLabel
{
    case Running = 'Running';
    case Ended = 'Ended';
    case ToBeDetermined = 'To Be Determined';
    case InDevelopment = 'In Development';

    public function getLabel(): string
    {
        return $this->value;
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Running => 'green',
            self::Ended => 'red',
            self::ToBeDetermined => 'yellow',
            self::InDevelopment => 'blue',
        };
    }

    public function getIcon(): string
    {
        return 'lucide-'.$this->icon();
    }

    public function icon(): string
    {
        return match ($this) {
            self::Running => 'tv-minimal-play',
            self::Ended => 'circle-check',
            self::ToBeDetermined => 'circle-alert',
            self::InDevelopment => 'hammer',
        };
    }

    public function iconColorClass(): string
    {
        return match ($this) {
            self::Running => 'text-green-400',
            self::Ended => 'text-red-400',
            self::ToBeDetermined => 'text-yellow-400',
            self::InDevelopment => 'text-blue-400',
        };
    }
}
