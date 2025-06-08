<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\StreamService;
use Illuminate\Support\Facades\Log;

class FetchIcecastMetrics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'icecast:metrics';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch metrics from Icecast server and publish to Kafka';

    /**
     * The stream service instance.
     *
     * @var StreamService
     */
    protected $streamService;

    /**
     * Create a new command instance.
     *
     * @param StreamService $streamService
     * @return void
     */
    public function __construct(StreamService $streamService)
    {
        parent::__construct();
        $this->streamService = $streamService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Fetching Icecast metrics...');

        try {
            // Fetch metrics and publish to Kafka
            $this->streamService->fetchIcecastMetrics();
            
            $this->info('Metrics fetched and published to Kafka successfully.');
            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to fetch metrics: ' . $e->getMessage());
            Log::error('Failed to fetch Icecast metrics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }
}
