<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MediaGenre extends Model
{
    use SoftDeletes;

    protected $table = 'media_genres';

    protected $fillable = [
        'media_id',
        'genre_id'
    ];
} 