<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Movie extends Model
{
    /** @use HasFactory<\Database\Factories\MovieFactory> */
    use HasFactory;

    protected $fillable = [
        'tmdb_id',
        'title',
        'popularity',
        'video',
    ];

    protected function casts(): array
    {
        return [
            'popularity' => 'float',
            'video' => 'boolean',
        ];
    }
}
