<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Channel extends Model
{
    use SoftDeletes; // Enable soft deletes

    protected $fillable = [
        'name',
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
            // Generate a unique hash from user_id and name
            $baseSlug = Str::slug($channel->name);
            $channel->slug = $channel->generateUniqueSlug($baseSlug);
        });

        static::deleting(function ($channel) {
            // Delete associated channel_media entries
            $channel->media()->detach();
        });
    }

    /**
     * Generate a unique slug using user_id and name
     */
    protected function generateUniqueSlug(string $baseSlug): string
    {
        // Create a unique string combining user_id and slug
        $uniqueString = $this->user_id . '-' . $baseSlug;
        
        // Generate a short hash
        $hash = substr(md5($uniqueString), 0, 8);
        
        // Combine base slug with hash
        return $baseSlug . '-' . $hash;
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
     * Get channel by ID or slug
     */
    public function resolveRouteBinding($value, $field = null)
    {
        // First try to find by slug
        $channel = $this->where('slug', $value)->first();
        
        // If not found by slug, try to find by ID
        if (!$channel && is_numeric($value)) {
            $channel = $this->where('id', $value)->first();
        }
        
        // If still not found, throw 404
        if (!$channel) {
            abort(404, 'Channel not found');
        }
        
        return $channel;
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        // Default to slug for route model binding
        return 'slug';
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

    public function media()
    {
        return $this->belongsToMany(Media::class, 'channel_media')
            ->withTimestamps()
            ->withPivot('active', 'list_order');
    }

    public function genres()
    {
        return $this->belongsToMany(Genre::class, 'channel_genre'); // Ensure the pivot table name is correct
    }
} 