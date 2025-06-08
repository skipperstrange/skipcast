<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\KafkaConsumerService;
use Illuminate\Support\Facades\Log;

class ConsumeKafkaMessages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kafka:consume {--topic=* : The topics to consume from}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Consume messages from Kafka topics';

    /**
     * The Kafka consumer service instance.
     *
     * @var KafkaConsumerService
     */
    protected $consumerService;

    /**
     * Create a new command instance.
     *
     * @param KafkaConsumerService $consumerService
     * @return void
     */
    public function __construct(KafkaConsumerService $consumerService)
    {
        parent::__construct();
        $this->consumerService = $consumerService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Get topics from options or use all configured topics
        $topics = $this->option('topic');
        if (empty($topics)) {
            $topics = array_values(config('kafka.topics'));
        }

        $this->info('Starting Kafka consumer for topics: ' . implode(', ', $topics));

        // Register handlers for different event types
        $this->registerEventHandlers();

        try {
            // Start consuming messages
            $this->consumerService->consume($topics);
            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to consume Kafka messages: ' . $e->getMessage());
            Log::error('Kafka consumer error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    /**
     * Register handlers for different event types
     */
    protected function registerEventHandlers()
    {
        // Channel streaming events
        $this->consumerService->registerHandler('channel_started', function ($data) {
            $this->info("Channel {$data['channel_slug']} started streaming");
            Log::info('Channel started streaming', $data);
        });

        $this->consumerService->registerHandler('channel_stopped', function ($data) {
            $this->info("Channel {$data['channel_slug']} stopped streaming");
            Log::info('Channel stopped streaming', $data);
        });

        // Listener metrics events
        $this->consumerService->registerHandler('listener_count_update', function ($data) {
            $this->info("Channel {$data['channel_slug']} has {$data['count']} listeners");
            Log::info('Listener count updated', $data);
        });

        // User interaction events
        $this->consumerService->registerHandler('media_liked', function ($data) {
            $this->info("Media {$data['media_id']} was liked by user {$data['user_id']}");
            Log::info('Media liked', $data);
        });

        $this->consumerService->registerHandler('channel_liked', function ($data) {
            $this->info("Channel {$data['channel_id']} was liked by user {$data['user_id']}");
            Log::info('Channel liked', $data);
        });
    }
}
