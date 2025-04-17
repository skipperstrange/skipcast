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
        'media_type',
        'resolution',
        'codec',
        'frame_rate',
        'aspect_ratio',
        'bitrate',
    ];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($media) {
            // Delete associated channel_media entries
            $media->channels()->detach();
        });
    }

    public function isAudio()
    {
        return $this->media_type === 'audio';
    }

    public function isVideo()
    {
        return $this->media_type === 'video';
    }

    public function isImage()
    {
        return $this->media_type === 'image';
    }

    public function channels()
    {
        return $this->belongsToMany(Channel::class, 'channel_media')
            ->withTimestamps()
            ->withPivot('active', 'list_order'); // Include any additional pivot fields
    }
} 