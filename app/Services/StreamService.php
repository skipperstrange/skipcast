<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\Media;
use Illuminate\Support\Facades\Log;

class StreamService
{
    protected $kafkaProducer;

    public function __construct(KafkaProducerService $kafkaProducer)
    {
        $this->kafkaProducer = $kafkaProducer;
    }
    
    /**
     * Start streaming a channel
     */
    public function startStream(Channel $channel): bool
    {
        try {
            // Publish the command to start the stream
            $this->kafkaProducer->publish('stream_commands', [
                'command' => 'start_stream',
                'channel_id' => $channel->id,
                'channel_slug' => $channel->slug,
                'timestamp' => time(),
                'metadata' => [
                    'user_id' => $channel->user_id,
                    'privacy' => $channel->privacy,
                ]
            ]);

            // Publish the event that streaming has started
            $this->kafkaProducer->publish('stream_events', [
                'event' => 'channel_started',
                'channel_id' => $channel->id,
                'channel_slug' => $channel->slug,
                'timestamp' => time(),
                'metadata' => [
                    'user_id' => $channel->user_id,
                    'privacy' => $channel->privacy,
                ]
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to start stream', [
                'error' => $e->getMessage(),
                'channel' => $channel->slug
            ]);
            throw $e;
        }
    }
    
    /**
     * Stop streaming a channel
     */
    public function stopStream(Channel $channel): bool
    {
        try {
            // Publish the command to stop the stream
            $this->kafkaProducer->publish('stream_commands', [
                'command' => 'stop_stream',
                'channel_id' => $channel->id,
                'channel_slug' => $channel->slug,
                'timestamp' => time(),
                'metadata' => [
                    'user_id' => $channel->user_id,
                    'privacy' => $channel->privacy,
                ]
            ]);

            // Publish the event that streaming has stopped
            $this->kafkaProducer->publish('stream_events', [
                'event' => 'channel_stopped',
                'channel_id' => $channel->id,
                'channel_slug' => $channel->slug,
                'timestamp' => time(),
                'metadata' => [
                    'user_id' => $channel->user_id,
                    'privacy' => $channel->privacy,
                ]
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to stop stream', [
                'error' => $e->getMessage(),
                'channel' => $channel->slug
            ]);
            throw $e;
        }
    }

    /**
     * Check the status of a stream
     */
    public function checkStreamStatus(Channel $channel): void
    {
        try {
            $this->kafkaProducer->publish('stream_commands', [
                'command' => 'check_stream_status',
                'channel_id' => $channel->id,
                'channel_slug' => $channel->slug,
                'timestamp' => time()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to check stream status', [
                'error' => $e->getMessage(),
                'channel' => $channel->slug
            ]);
            throw $e;
        }
    }
    
    /**
     * Update listener count for a channel
     */
    public function updateListenerCount(Channel $channel, int $count)
    {
        return $this->kafkaProducer->publish('listener_metrics', [
            'event' => 'listener_count_update',
            'channel_id' => $channel->id,
            'channel_slug' => $channel->slug,
            'count' => $count,
            'timestamp' => time(),
            'metadata' => [
                'user_id' => $channel->user_id,
                'privacy' => $channel->privacy,
            ]
        ]);
    }
    
    /**
     * Publish event when media is liked
     */
    public function mediaLiked(Media $media, $userId)
    {
        return $this->kafkaProducer->publish('user_interactions', [
            'event' => 'media_liked',
            'media_id' => $media->id,
            'user_id' => $userId,
            'timestamp' => time(),
            'metadata' => [
                'media_type' => $media->media_type,
                'owner_id' => $media->user_id,
            ]
        ]);
    }
    
    /**
     * Publish event when a channel is liked
     */
    public function channelLiked(Channel $channel, $userId)
    {
        return $this->kafkaProducer->publish('user_interactions', [
            'event' => 'channel_liked',
            'channel_id' => $channel->id,
            'user_id' => $userId,
            'timestamp' => time(),
            'metadata' => [
                'owner_id' => $channel->user_id,
                'privacy' => $channel->privacy,
            ]
        ]);
    }
    
    
    /**
     * Fetch metrics from Icecast server
     */
    public function fetchIcecastMetrics()
    {
        try {
            $host = config('liquidsoap.host');
            $port = config('liquidsoap.port');
            $url = "http://$host:$port/status-json.xsl";
            
            $response = file_get_contents($url);
            $stats = json_decode($response, true);
            
            if (!$stats) {
                throw new \Exception('Failed to parse Icecast stats');
            }
            
            // Process each mount point
            foreach ($stats['icestats']['source'] ?? [] as $source) {
                // Extract channel slug from mount point
                $channelSlug = str_replace('/', '', $source['mount']);
                
                // Find the channel
                $channel = Channel::where('slug', $channelSlug)->first();
                
                if ($channel) {
                    // Update listener count
                    $this->updateListenerCount($channel, $source['listeners'] ?? 0);
                }
            }
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to fetch Icecast metrics: ' . $e->getMessage());
            throw $e;
        }
    }
}
