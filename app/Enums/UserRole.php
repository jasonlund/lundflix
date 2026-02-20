<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

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

    public function getIcon(): string
    {
        return match ($this) {
            self::Admin => 'lucide-shield-check',
            self::ServerOwner => 'lucide-server',
            self::Member => 'lucide-user',
        };
    }
}
