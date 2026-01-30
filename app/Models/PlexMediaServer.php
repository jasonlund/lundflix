<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlexMediaServer extends Model
{
    /** @use HasFactory<\Database\Factories\PlexMediaServerFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'owned' => 'boolean',
            'is_online' => 'boolean',
            'visible' => 'boolean',
            'connections' => 'array',
            'last_seen_at' => 'datetime',
        ];
    }
}
