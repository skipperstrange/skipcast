<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\Media;
use Illuminate\Support\Facades\Auth;

class ChannelMediaService
{
    /**
     * Attach media to a channel with permission checks
     */
    public function attachMedia(Channel $channel, array $mediaIds): bool
    {
        // Validate that all media IDs exist
        $mediaItems = Media::whereIn('id', $mediaIds)->get();

        // Check if any media IDs are invalid
        if ($mediaItems->count() !== count($mediaIds)) {
            $invalidIds = array_diff($mediaIds, $mediaItems->pluck('id')->toArray());
            throw new \Exception("The following media IDs do not exist: " . implode(', ', $invalidIds));
        }

        // Check permissions for each media item
        foreach ($mediaItems as $media) {
            if (!$this->canAttachMedia($channel, $media)) {
                throw new \Exception("Cannot attach media: {$media->title}. You can only attach your own media or public media to your channels.");
            }
        }

        // Attach media to the channel
        $channel->media()->sync($mediaIds);
        return true;
    }

    /**
     * Detach media from a channel with permission checks
     */
    public function detachMedia(Channel $channel, array $mediaIds): bool
    {
        // Detach media from the channel
        $channel->media()->detach($mediaIds);
        return true;
    }

    /**
     * Check if media can be attached/detached to/from channel
     */
    private function canAttachMedia(Channel $channel, Media $media): bool
    {
        return $channel->user_id === Auth::id() && 
               ($media->public === 'public' || $media->user_id === Auth::id());
    }

    public function attachGenres(Channel $channel, array $genreIds): bool
    {
        // Attach genres to the channel
        $channel->genres()->attach($genreIds);
        return true;
    }

} 