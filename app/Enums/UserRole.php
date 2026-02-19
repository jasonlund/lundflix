<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum UserRole: string implements HasColor, HasIcon, HasLabel
{
    case Admin = 'admin';
    case ServerOwner = 'server_owner';
    case Member = 'member';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::ServerOwner => 'Server Owner',
            self::Member => 'Member',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Admin => 'warning',
            self::ServerOwner => 'info',
            self::Member => 'gray',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Admin => Heroicon::ShieldCheck,
            self::ServerOwner => Heroicon::Server,
            self::Member => Heroicon::User,
        };
    }
}
