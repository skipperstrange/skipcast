<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChannelGenre extends Model
{
    use SoftDeletes;

    protected $table = 'channel_genres';

    protected $fillable = [
        'channel_id',
        'genre_id'
    ];
} 