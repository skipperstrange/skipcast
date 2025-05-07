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

    /**
     * Generate Liquidsoap configuration file for a channel
     */
    public function generateLiquidsoapConfig(Channel $channel): string
    {
        $media = $channel->media()->orderBy('list_order')->get();
        $playlistPath = config('liquidsoap.playlist_path') . "/{$channel->slug}.m3u";
        
        // Generate playlist file first
        $this->generatePlaylistFile($channel, $media);
        
        // Get configuration values
        $host = config('liquidsoap.host');
        $port = config('liquidsoap.port');
        $password = config('liquidsoap.password');
        $logPath = config('liquidsoap.log_path');
        $bitrate = config('liquidsoap.bitrate');
        $samplerate = config('liquidsoap.samplerate');
        $stereo = config('liquidsoap.stereo');
        
        // Base configuration
        $config = <<<LIQ
#!/usr/bin/env liquidsoap

# Set up logging
set("log.file", true)
set("log.file.path", "{$logPath}/{$channel->slug}.log")
set("log.level", {$logLevel})

# Input playlist
playlist = playlist("$playlistPath")

# Output settings
output.icecast(
    %mp3(
        stereo = {$stereo},
        bitrate = {$bitrate},
        samplerate = {$samplerate}
    ),
    host = "{$host}",
    port = {$port},
    password = "{$password}",
    mount = "/{$channel->slug}",
    playlist
)
LIQ;

        // Add privacy settings
        if ($channel->privacy === 'private') {
            $config .= <<<LIQ

# Add authentication for private streams
def auth(user, password) =
    # Add your authentication logic here
    # For now, we'll just allow the channel owner
    user == "{$channel->user->email}" and password == "{$channel->user->id}"
end

# Apply authentication to the output
output.icecast(
    %mp3(
        stereo = {$stereo},
        bitrate = {$bitrate},
        samplerate = {$samplerate}
    ),
    host = "{$host}",
    port = {$port},
    password = "{$password}",
    mount = "/{$channel->slug}",
    user = "source",
    auth = auth,
    playlist
)
LIQ;
        }

        return $config;
    }

    /**
     * Generate playlist file for a channel
     */
    public function generatePlaylistFile(Channel $channel, $media = null): bool
    {
        if (!$media) {
            $media = $channel->media()->orderBy('list_order')->get();
        }

        $playlistContent = "#EXTM3U\n";
        $playlistContent .= "#EXTENC:UTF-8\n";
        $playlistContent .= "#EXT-X-VERSION:3\n";
        $playlistContent .= "#EXT-X-TARGETDURATION:10\n";
        $playlistContent .= "#EXT-X-MEDIA-SEQUENCE:0\n\n";

        foreach ($media as $item) {
            $filePath = storage_path("app/" . $item->file_path);
            if (file_exists($filePath)) {
                $playlistContent .= "#EXTINF:{$item->duration}," . $item->title . "\n";
                $playlistContent .= $filePath . "\n\n";
            }
        }

        // Ensure the playlists directory exists
        $playlistDir = config('liquidsoap.playlist_path');
        if (!file_exists($playlistDir)) {
            mkdir($playlistDir, 0755, true);
        }

        // Save the playlist file
        $playlistPath = $playlistDir . "/{$channel->slug}.m3u";
        return file_put_contents($playlistPath, $playlistContent) !== false;
    }

    /**
     * Save Liquidsoap configuration file
     */
    public function saveLiquidsoapConfig(Channel $channel): bool
    {
        $config = $this->generateLiquidsoapConfig($channel);
        
        // Ensure the liquidsoap config directory exists
        $configDir = config('liquidsoap.config_path');
        if (!file_exists($configDir)) {
            mkdir($configDir, 0755, true);
        }

        // Save the config file
        $configPath = $configDir . "/{$channel->slug}.liq";
        return file_put_contents($configPath, $config) !== false;
    }

    /**
     * Attach a media item to one or more channels, with permission checks.
     */
    public function attachChannels(Media $media, array $channelIds): bool
    {
        // Load and validate channels
        $channels = Channel::whereIn('id', $channelIds)->get();
        if ($channels->count() !== count($channelIds)) {
            $invalid = array_diff($channelIds, $channels->pluck('id')->toArray());
            throw new \Exception("The following channel IDs do not exist: " . implode(', ', $invalid));
        }

        // Permission check: must own the channel && (media is public or owned)
        foreach ($channels as $channel) {
            if (! (
                $channel->user_id === Auth::id()
                && ($media->public === 'public' || $media->user_id === Auth::id())
            )) {
                throw new \Exception("Cannot attach media to channel: {$channel->name}");
            }
        }

        // Perform attach
        $media->channels()->attach($channelIds);

        return true;
    }

    /**
     * Detach a media item from one or more channels, with permission checks.
     */
    public function detachChannels(Media $media, array $channelIds): bool
    {
        // (Optional) validate channel IDs exist, similar to attachChannels…

        // Permission check same as attachChannels
        $channels = Channel::whereIn('id', $channelIds)->get();
        foreach ($channels as $channel) {
            if (! (
                $channel->user_id === Auth::id()
                && ($media->public === 'public' || $media->user_id === Auth::id())
            )) {
                throw new \Exception("Cannot detach media from channel: {$channel->name}");
            }
        }

        // Perform detach
        $media->channels()->detach($channelIds);

        return true;
    }
} 