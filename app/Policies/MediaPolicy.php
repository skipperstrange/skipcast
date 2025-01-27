<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Media;

class MediaPolicy
{
    /**
     * Determine if the user can update the media.
     */
    public function update(User $user, Media $media): bool
    {
        return $user->id === $media->user_id; // Only the owner can update
    }
} 