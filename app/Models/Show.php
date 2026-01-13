<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Show extends Model
{
    /** @use HasFactory<\Database\Factories\ShowFactory> */
    use HasFactory;

    protected $fillable = [
        'tvmaze_id',
        'name',
        'type',
        'language',
        'genres',
        'status',
        'runtime',
        'premiered',
        'ended',
        'official_site',
        'schedule',
        'rating',
        'weight',
        'network',
        'web_channel',
        'externals',
        'image',
        'summary',
        'updated_at_tvmaze',
    ];

    protected function casts(): array
    {
        return [
            'genres' => 'array',
            'schedule' => 'array',
            'rating' => 'array',
            'network' => 'array',
            'web_channel' => 'array',
            'externals' => 'array',
            'image' => 'array',
            'premiered' => 'date',
            'ended' => 'date',
        ];
    }
}
