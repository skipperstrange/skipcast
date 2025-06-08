<?php

namespace App\Services;

use RdKafka\Conf;
use RdKafka\Producer;
use RdKafka\KafkaConsumer;
use App\Models\Channel;
use App\Models\Media;
use Illuminate\Support\Facades\Log;

class StreamService
{
    protected $producer;
    protected $consumer;
    
    public function __construct()
    {
        $this->initProducer();
    }
    
    /**
     * Initialize the Kafka producer
     */
    private function initProducer()
    {
        try {
            $conf = new Conf();
            $conf->set('metadata.broker.list', config('kafka.broker'));
            $conf->set('log_level', (string) LOG_DEBUG);
            $conf->set('debug', 'all');
            
            $this->producer = new Producer($conf);
        } catch (\Exception $e) {
            Log::error('Failed to initialize Kafka producer: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Start streaming a channel
     */
    public function startStream(Channel $channel): bool
    {
        try {
            // First publish the command to start the stream
            $this->publishEvent('stream_commands', [
                'command' => 'start_stream',
                'channel_id' => $channel->id,
                'channel_slug' => $channel->slug,
                'timestamp' => time(),
                'metadata' => [
                    'user_id' => $channel->user_id,
                    'privacy' => $channel->privacy,
                ]
            ]);

            // Then publish the event that streaming has started
            $this->publishEvent('stream_events', [
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
            // First publish the command to stop the stream
            $this->publishEvent('stream_commands', [
                'command' => 'stop_stream',
                'channel_id' => $channel->id,
                'channel_slug' => $channel->slug,
                'timestamp' => time(),
                'metadata' => [
                    'user_id' => $channel->user_id,
                    'privacy' => $channel->privacy,
                ]
            ]);

            // Then publish the event that streaming has stopped
            $this->publishEvent('stream_events', [
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
            $this->publishEvent('stream_commands', [
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
        return $this->publishEvent('listener_metrics', [
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
        return $this->publishEvent('user_interactions', [
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
        return $this->publishEvent('user_interactions', [
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
     * Generic method to publish events to Kafka
     */
    private function publishEvent(string $topicName, array $data)
    {
        try {
            $topic = $this->producer->newTopic(config("kafka.topics.$topicName"));
            
            // Add common fields to all events
            $data = array_merge($data, [
                'application' => 'skipcast',
                'version' => '1.0',
                'environment' => config('app.env'),
            ]);
            
            // Produce message
            $topic->produce(RD_KAFKA_PARTITION_UA, 0, json_encode($data));
            
            // Poll to handle delivery reports
            $this->producer->poll(0);
            
            if (config('kafka.debug')) {
                Log::debug('Kafka event published', [
                    'topic' => $topicName,
                    'data' => $data
                ]);
            }
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to publish Kafka event', [
                'topic' => $topicName,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
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
