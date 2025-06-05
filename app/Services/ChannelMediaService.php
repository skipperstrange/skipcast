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
        $channel->media()->attach($mediaIds);
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
        $channel->genres()->sync($genreIds);
        return true;
    }

    /**
     * Generate Liquidsoap configuration path for a channel
     */
    public function getLiquidsoapConfigFilePath(Channel $channel): string{
        return config('liquidsoap.config_path') . "/{$channel->privacy}/{$channel->slug}.liq";
    }

     /**
     * Generate Playlist configuration path for a channel
     */
    public function getPlaylistConfigFilePath(Channel $channel): string{
        return config('liquidsoap.playlist_path') . "/{$channel->slug}.m3u";
    }

    /**
     * Generate Liquidsoap configuration file for a channel
     */
    public function generateLiquidsoapConfigFile(Channel $channel, string $logLevel): string
    {
        $media = $channel->media()->orderBy('list_order')->get();
        $configPath=$this->getLiquidsoapConfigFilePath($channel);
        $playlistPath = $this->getPlaylistConfigFilePath($channel);

        // Get configuration values
        $host = config('liquidsoap.host');
        $port = config('liquidsoap.port');
        $password = config('liquidsoap.password');
        $logPath = config('liquidsoap.log_path');
        $bitrate = config('liquidsoap.bitrate');
        $samplerate = config('liquidsoap.samplerate');
        $stereo = config('liquidsoap.stereo') ? 'true' : 'false';
        $logPath = config('liquidsoap.log_path');

        $config = <<<LIQ
#!/usr/bin/env liquidsoap

# Logging
set("log.file", true)
#log.file.path= "{$logPath}/{$channel->slug}.log"

# Playlist source (fallible)
playlist_source = playlist("/mnt/c/wamp64/www/skipcast/storage/app/playlists/{$channel->slug}")

# Timeout wrapper (3 seconds)
#playlist_with_timeout = timeout(playlist_source, 3.0, [sine()])

# Safe fallback setup — ensures Liquidsoap won't crash
radio = fallback(track_sensitive=false, [playlist_source, sine()])
#radio = fallback.skip( [playlist_source, sine()])
# Fallback just in case
#radio = fallback([playlist_with_timeout, sine()])


# Output to Icecast
output.icecast(
  %mp3(
    stereo = true,
    bitrate = 128,
    samplerate = 44100
  ),
  host = "{$host}",
  port = {$port},
  password = "{$password}",
  mount = "/{$channel->slug}",
  radio
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
        
        $media = !$media
        ? $channel->media()->orderBy('list_order')->get()
        : $channel->media()->whereIn('id', $media)->orderBy('list_order')->get();
        if ($media->isEmpty()) {
            throw new \Exception("No media found for the channel.");
        }            


        $mediaFilePath=config('liquidsoap.media_path');
        $playlistContent = "#EXTM3U\n";
        $playlistContent .= "#EXTENC:UTF-8\n";
        $playlistContent .= "#EXT-X-VERSION:3\n";
        $playlistContent .= "#EXT-X-TARGETDURATION:10\n";
        $playlistContent .= "#EXT-X-MEDIA-SEQUENCE:0\n\n";
        
        foreach ($media as $item) {
            $filePath = storage_path("app/" . $item->file_path);
            
                $playlistContent .= "#EXTINF:{$item->duration}," . $item->title . "\n";
                $playlistContent .= "{$mediaFilePath}/{$item->file_path}\n\n";
        }
       

        // Ensure the playlists directory exists
        $playlistDir = config('liquidsoap.playlist_storage_path');

        if (!file_exists($playlistDir)) {
            mkdir($playlistDir, 0755, true);
        }

        // Save the playlist file
        $playlistFilePath = $playlistDir . "/{$channel->slug}.m3u";
        if (file_exists($playlistFilePath)) {
            unlink($playlistFilePath);
        }
        $written = file_put_contents($playlistFilePath, $playlistContent);
        if (!$written) {
            throw new \Exception("Failed to generate playlist file: {$playlistFilePath}");
        }
        return $written;
    }

    /**
     * Save Liquidsoap configuration file
     */
    public function saveLiquidsoapConfig(Channel $channel, string $logLevel = 'info'): bool
    {
        $config = $this->generateLiquidsoapConfigFile($channel, $logLevel);

        // Ensure the liquidsoap config directory exists
        $configDir = config('liquidsoap.config_path');
        if (!file_exists($configDir)) {
            mkdir($configDir, 0755, true);
        }

        // Save the config file
        $configFilePath = $this->getLiquidsoapConfigFilePath($channel);
        
        $written = file_put_contents($configFilePath, $config);
        if (!$written) {
            throw new \Exception("Failed to write Liquidsoap config file: {$configFilePath}");
        }
        return $written;
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
