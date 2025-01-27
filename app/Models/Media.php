<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    protected $fillable = [
        'title',
        'track',
        'album',
        'year',
        'artist',
        'type',
        'channelmode',
        'public',
        'downloadable',
        'size',
        'views',
        'likes',
        'user_id',
        'duration',
    ];
} 