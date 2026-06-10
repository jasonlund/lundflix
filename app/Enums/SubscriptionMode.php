<?php

declare(strict_types=1);

namespace App\Enums;

enum SubscriptionMode: string
{
    case Download = 'download';
    case Notify = 'notify';

    public function label(): string
    {
        return match ($this) {
            self::Download => 'Subscribed',
            self::Notify => 'Notify',
        };
    }

    public function menuLabel(): string
    {
        return match ($this) {
            self::Download => 'Add & Notify',
            self::Notify => 'Notify Only',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Download => 'heart',
            self::Notify => 'bell',
        };
    }

    public function isFilled(): bool
    {
        return $this === self::Download;
    }
}
