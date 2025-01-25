<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Channel extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'genre',
        'user_id',
        'state',
        'active',
        'privacy',
        'max_listeners',
        'current_listeners',
    ];

    protected $casts = [
        'max_listeners' => 'integer',
        'current_listeners' => 'integer',
        'views' => 'integer',
        'likes' => 'integer',
    ];

    // Channel states
    const STATE_ON = 'on';
    const STATE_OFF = 'off';

    // Channel visibility states
    const ACTIVE = 'active';
    const INACTIVE = 'inactive';
    const TRASH = 'trash';

    // Privacy settings
    const PRIVATE = 'private';
    const PUBLIC = 'public';

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($channel) {
            if (! $channel->slug) {
                $channel->slug = Str::slug($channel->name);
            }
        });

        static::updating(function ($channel) {
            if ($channel->isDirty('name') && ! $channel->isDirty('slug')) {
                $channel->slug = Str::slug($channel->name);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if channel is currently streaming
     */
    public function isStreaming(): bool
    {
        return $this->state === self::STATE_ON;
    }

    /**
     * Check if channel is visible and available
     */
    public function isAvailable(): bool
    {
        return $this->active === self::ACTIVE;
    }

    /**
     * Check if channel is public
     */
    public function isPublic(): bool
    {
        return $this->privacy === self::PUBLIC;
    }

    /**
     * Get channel by slug
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where('slug', $value)->firstOrFail();
    }

    /**
     * Increment view count
     */
    public function incrementViews(): void
    {
        $this->increment('views');
    }

    /**
     * Increment like count
     */
    public function incrementLikes(): void
    {
        $this->increment('likes');
    }
} 