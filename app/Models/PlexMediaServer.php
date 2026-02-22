<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PlexMediaServer extends Model
{
    /** @use HasFactory<\Database\Factories\PlexMediaServerFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * Get the Plex web app URL for this server's dashboard.
     */
    public function webUrl(): string
    {
        return "https://app.plex.tv/desktop/#!/media/{$this->client_identifier}/com.plexapp.plugins.library?key=%2Fhubs&pageType=hub";
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('plex:visible-servers'));
    }

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
            'plex_last_seen_at' => 'datetime',
        ];
    }
}
