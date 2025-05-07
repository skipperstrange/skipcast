<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Media;

class MediaPolicy
{
    /**
     * Determine if the user can view the media.
     */
    public function view(User $user, Media $media): bool
    {
        // Allow if media is public or user owns it
        return $media->public === 'public' || $user->id === $media->user_id;
    }

    /**
     * Determine if the user can create media.
     */
    public function create(User $user): bool
    {
        return true; // Any authenticated user can create media
    }

    /**
     * Determine if the user can update the media.
     */
    public function update(User $user, Media $media): bool
    {
        return $user->id === $media->user_id; // Only the owner can update
    }

    /**
     * Determine if the user can delete the media.
     */
    public function delete(User $user, Media $media): bool
    {
        return $user->id === $media->user_id; // Only the owner can delete
    }

    /**
     * Determine if the user can restore the media.
     */
    public function restore(User $user, Media $media): bool
    {
        return $user->id === $media->user_id; // Only the owner can restore
    }

    /**
     * Determine if the user can view trashed media.
     */
    public function viewTrashed(User $user, Media $media): bool
    {
        return $user->id === $media->user_id; // Only the owner can view their trashed media
    }
} 