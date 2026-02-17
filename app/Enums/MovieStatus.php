<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum MovieStatus: string implements HasColor, HasIcon, HasLabel
{
    case Rumored = 'Rumored';
    case Planned = 'Planned';
    case InProduction = 'In Production';
    case PostProduction = 'Post Production';
    case FestivalRelease = 'Festival Release';
    case LimitedRelease = 'Limited Release';
    case Upcoming = 'Upcoming';
    case InTheaters = 'In Theaters';
    case Released = 'Released';
    case Canceled = 'Canceled';

    public function getLabel(): string
    {
        return $this->value;
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Rumored => 'gray',
            self::Planned, self::Upcoming => 'blue',
            self::InProduction => 'yellow',
            self::PostProduction => 'warning',
            self::FestivalRelease => 'purple',
            self::LimitedRelease => 'teal',
            self::InTheaters => 'green',
            self::Released => 'success',
            self::Canceled => 'red',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Rumored => Heroicon::ChatBubbleLeftEllipsis,
            self::Planned => Heroicon::ClipboardDocumentList,
            self::InProduction => Heroicon::Film,
            self::PostProduction => Heroicon::AdjustmentsHorizontal,
            self::FestivalRelease => Heroicon::Sparkles,
            self::LimitedRelease => Heroicon::MapPin,
            self::Upcoming => Heroicon::Calendar,
            self::InTheaters => Heroicon::Ticket,
            self::Released => Heroicon::CheckCircle,
            self::Canceled => Heroicon::XCircle,
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Rumored => 'chat-bubble-left-ellipsis',
            self::Planned => 'clipboard-document-list',
            self::InProduction => 'film',
            self::PostProduction => 'adjustments-horizontal',
            self::FestivalRelease => 'sparkles',
            self::LimitedRelease => 'map-pin',
            self::Upcoming => 'calendar',
            self::InTheaters => 'ticket',
            self::Released => 'check-circle',
            self::Canceled => 'x-circle',
        };
    }

    public function isCartable(): bool
    {
        return $this === self::Released;
    }

    public function iconColorClass(): string
    {
        return match ($this) {
            self::Rumored => 'text-zinc-400',
            self::Planned, self::Upcoming => 'text-blue-400',
            self::InProduction => 'text-yellow-400',
            self::PostProduction => 'text-orange-400',
            self::FestivalRelease => 'text-purple-400',
            self::LimitedRelease => 'text-teal-400',
            self::InTheaters => 'text-green-400',
            self::Released => 'text-emerald-400',
            self::Canceled => 'text-red-400',
        };
    }
}
