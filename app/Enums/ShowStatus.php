<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

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

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Running => Heroicon::PlayCircle,
            self::Ended => Heroicon::CheckCircle,
            self::ToBeDetermined => Heroicon::QuestionMarkCircle,
            self::InDevelopment => Heroicon::Wrench,
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Running => 'circle-play',
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
