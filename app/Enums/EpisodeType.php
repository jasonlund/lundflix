<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EpisodeType: string implements HasColor, HasLabel
{
    case Regular = 'regular';
    case SignificantSpecial = 'significant_special';
    case InsignificantSpecial = 'insignificant_special';

    public function getLabel(): string
    {
        return match ($this) {
            self::Regular => 'Regular',
            self::SignificantSpecial => 'Special',
            self::InsignificantSpecial => 'Insignificant Special',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Regular => 'gray',
            self::SignificantSpecial => 'warning',
            self::InsignificantSpecial => 'gray',
        };
    }
}
