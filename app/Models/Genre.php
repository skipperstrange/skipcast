<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Genre extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'genre',
        'slug'
    ];

    public function channels()
    {
        return $this->belongsToMany(Channel::class, 'channel_genres')
            ->withTimestamps();
    }

    public function media()
    {
        return $this->belongsToMany(Media::class, 'media_genres')
            ->withTimestamps();
    }
} 