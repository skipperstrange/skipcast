<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Media extends Model
{
    use SoftDeletes; // Enable soft deletes

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
        'cover_art',
        'filename',
        'file_path',
    ];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($media) {
            // Delete associated channel_media entries
            $media->channels()->detach();
        });
    }
} 