<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Movie extends Model
{
    /** @use HasFactory<\Database\Factories\MovieFactory> */
    use HasFactory;

    protected $fillable = [
        'imdb_id',
        'title',
        'year',
        'runtime',
        'genres',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'runtime' => 'integer',
        ];
    }
}
