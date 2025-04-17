<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\Media;
use App\Models\Genre;
use Illuminate\Support\Facades\Auth;

class GenreService
{
    /**
     * Attach genres to a channel.
     */
    public function attachToChannel(Channel $channel, array $genreIds): bool
    {
        // Validate that all genre IDs exist
        $genres = Genre::whereIn('id', $genreIds)->get();

        if ($genres->count() !== count($genreIds)) {
            $invalidIds = array_diff($genreIds, $genres->pluck('id')->toArray());
            throw new \Exception("The following genre IDs do not exist: " . implode(', ', $invalidIds));
        }

        // Get existing genre IDs for this channel
        $existingIds = $channel->genres()->pluck('id')->toArray();

        // Filter out IDs that are already attached
        $newIds = array_diff($genreIds, $existingIds);

        // Only attach new IDs if there are any
        if (!empty($newIds)) {
            $channel->genres()->attach($newIds);
        }

        return true;
    }

    /**
     * Detach genres from a channel.
     */
    public function detachFromChannel(Channel $channel, array $genreIds): bool
    {
        // Validate that all genre IDs exist
        $genres = Genre::whereIn('id', $genreIds)->get();

        if ($genres->count() !== count($genreIds)) {
            $invalidIds = array_diff($genreIds, $genres->pluck('id')->toArray());
            throw new \Exception("The following genre IDs do not exist: " . implode(', ', $invalidIds));
        }

        // Get existing genre IDs for this channel
        $existingIds = $channel->genres()->pluck('id')->toArray();

        // Filter to only detach IDs that are actually attached
        $idsToDetach = array_intersect($genreIds, $existingIds);

        // Only detach if there are any valid IDs to detach
        if (!empty($idsToDetach)) {
            $channel->genres()->detach($idsToDetach);
        }

        return true;
    }

    /**
     * Attach genres to media.
     */
    public function attachToMedia(Media $media, array $genreIds): bool
    {
        // Validate that all genre IDs exist
        $genres = Genre::whereIn('id', $genreIds)->get();

        if ($genres->count() !== count($genreIds)) {
            $invalidIds = array_diff($genreIds, $genres->pluck('id')->toArray());
            throw new \Exception("The following genre IDs do not exist: " . implode(', ', $invalidIds));
        }

        // Get existing genre IDs for this media
        $existingIds = $media->genres()->pluck('id')->toArray();

        // Filter out IDs that are already attached
        $newIds = array_diff($genreIds, $existingIds);

        // Only attach new IDs if there are any
        if (!empty($newIds)) {
            $media->genres()->attach($newIds);
        }

        return true;
    }

    /**
     * Detach genres from media.
     */
    public function detachFromMedia(Media $media, array $genreIds): bool
    {
        // Validate that all genre IDs exist
        $genres = Genre::whereIn('id', $genreIds)->get();

        if ($genres->count() !== count($genreIds)) {
            $invalidIds = array_diff($genreIds, $genres->pluck('id')->toArray());
            throw new \Exception("The following genre IDs do not exist: " . implode(', ', $invalidIds));
        }

        // Get existing genre IDs for this media
        $existingIds = $media->genres()->pluck('id')->toArray();

        // Filter to only detach IDs that are actually attached
        $idsToDetach = array_intersect($genreIds, $existingIds);

        // Only detach if there are any valid IDs to detach
        if (!empty($idsToDetach)) {
            $media->genres()->detach($idsToDetach);
        }

        return true;
    }

    public function manageChannelGenres(Channel $channel, array $genreIds, string $action = 'attach'): bool
    {
        // Validate IDs first
        $genres = Genre::whereIn('id', $genreIds)->get();

        if ($genres->count() !== count($genreIds)) {
            $invalidIds = array_diff($genreIds, $genres->pluck('id')->toArray());
            throw new \Exception("The following genre IDs do not exist: " . implode(', ', $invalidIds));
        }

        switch ($action) {
            case 'attach':
                $channel->genres()->attach($genreIds);
                break;
            case 'detach':
                $channel->genres()->detach($genreIds);
                break;
            case 'sync':
                $channel->genres()->sync($genreIds);
                break;
            default:
                throw new \Exception("Invalid action specified");
        }

        return true;
    }
} 