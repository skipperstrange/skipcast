<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\LiquidsoapCommandService;
use App\Services\KafkaConsumerService;
use App\Models\Channel;
use Illuminate\Support\Facades\Log;

class LiquidsoapCommandConsumer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'liquidsoap:command-consumer';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Consume Liquidsoap commands from Kafka';

    /**
     * The Liquidsoap command service instance.
     *
     * @var LiquidsoapCommandService
     */
    protected $liquidsoapService;

    /**
     * The Kafka consumer service instance.
     *
     * @var KafkaConsumerService
     */
    protected $kafkaConsumer;

    /**
     * Create a new command instance.
     */
    public function __construct(
        LiquidsoapCommandService $liquidsoapService,
        KafkaConsumerService $kafkaConsumer
    ) {
        parent::__construct();
        $this->liquidsoapService = $liquidsoapService;
        $this->kafkaConsumer = $kafkaConsumer;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting Liquidsoap command consumer...');

        // Register handlers for stream commands
        $this->registerCommandHandlers();

        try {
            // Start consuming messages from the stream_commands topic
            $this->kafkaConsumer->consume(['stream_commands']);
            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to consume Kafka messages: ' . $e->getMessage());
            Log::error('Liquidsoap command consumer error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    /**
     * Register handlers for different command types
     */
    protected function registerCommandHandlers(): void
    {
        // Handler for start stream command
        $this->kafkaConsumer->registerHandler('start_stream', function ($data) {
            try {
                $channel = Channel::where('slug', $data['channel_slug'])->firstOrFail();
                
                if ($this->liquidsoapService->isStreamRunning($channel)) {
                    $this->warn("Stream already running for channel {$channel->slug}");
                    return;
                }
                
                $this->info("Starting stream for channel {$channel->slug}");
                $this->liquidsoapService->startStream($channel);
                
                $this->info("Stream started successfully for channel {$channel->slug}");
            } catch (\Exception $e) {
                $this->error("Failed to start stream: " . $e->getMessage());
                Log::error('Failed to start stream', [
                    'error' => $e->getMessage(),
                    'data' => $data
                ]);
            }
        });

        // Handler for stop stream command
        $this->kafkaConsumer->registerHandler('stop_stream', function ($data) {
            try {
                $channel = Channel::where('slug', $data['channel_slug'])->firstOrFail();
                
                if (!$this->liquidsoapService->isStreamRunning($channel)) {
                    $this->warn("No active stream found for channel {$channel->slug}");
                    return;
                }
                
                $this->info("Stopping stream for channel {$channel->slug}");
                $this->liquidsoapService->stopStream($channel);
                
                $this->info("Stream stopped successfully for channel {$channel->slug}");
            } catch (\Exception $e) {
                $this->error("Failed to stop stream: " . $e->getMessage());
                Log::error('Failed to stop stream', [
                    'error' => $e->getMessage(),
                    'data' => $data
                ]);
            }
        });

        // Handler for check stream status command
        $this->kafkaConsumer->registerHandler('check_stream_status', function ($data) {
            try {
                $channel = Channel::where('slug', $data['channel_slug'])->firstOrFail();
                $isRunning = $this->liquidsoapService->isStreamRunning($channel);
                
                $status = $isRunning ? 'running' : 'stopped';
                $this->info("Stream status for channel {$channel->slug}: {$status}");
                
                // Publish status back to Kafka
                $this->kafkaConsumer->publish('stream_status', [
                    'event' => 'stream_status_update',
                    'channel_slug' => $channel->slug,
                    'status' => $status,
                    'timestamp' => time()
                ]);
            } catch (\Exception $e) {
                $this->error("Failed to check stream status: " . $e->getMessage());
                Log::error('Failed to check stream status', [
                    'error' => $e->getMessage(),
                    'data' => $data
                ]);
            }
        });
    }
}
