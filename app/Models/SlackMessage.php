<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SlackNotificationType;
use Database\Factories\SlackMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SlackMessage extends Model
{
    /** @use HasFactory<SlackMessageFactory> */
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => SlackNotificationType::class,
            'sent_at' => 'datetime',
        ];
    }
}
