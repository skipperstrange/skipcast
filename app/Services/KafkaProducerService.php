<?php

namespace App\Services;

use RdKafka\Conf;
use RdKafka\Producer;
use Illuminate\Support\Facades\Log;

class KafkaProducerService
{
    protected $producer;

    public function __construct()
    {
        $this->initProducer();
    }

    /**
     * Initialize the Kafka producer.
     */
    private function initProducer()
    {
        try {
            $conf = new Conf();
            $conf->set('metadata.broker.list', config('kafka.broker'));

            if (config('kafka.debug')) {
                $conf->set('log_level', (string) LOG_DEBUG);
                $conf->set('debug', 'all');
            }

            $this->producer = new Producer($conf);
        } catch (\Exception $e) {
            Log::error('Failed to initialize Kafka producer: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Publish a message to a Kafka topic.
     *
     * @param string $topicName The name of the topic (key from config/kafka.php).
     * @param array  $data      The data to publish.
     * @return bool
     */
    public function publish(string $topicName, array $data): bool
    {
        try {
            $topic = $this->producer->newTopic(config("kafka.topics.{$topicName}"));

            // Add common fields to all events
            $payload = array_merge($data, [
                'application' => config('app.name', 'Laravel'),
                'version' => '1.0',
                'environment' => config('app.env'),
            ]);

            // Produce message
            $topic->produce(RD_KAFKA_PARTITION_UA, 0, json_encode($payload));

            // Poll to handle delivery reports
            $this->producer->poll(0);

            if (config('kafka.debug')) {
                Log::debug('Kafka message published', [
                    'topic' => $topicName,
                    'data' => $payload
                ]);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to publish Kafka message', [
                'topic' => $topicName,
                'data' => $data,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Flush the producer queue.
     *
     * @param int $timeout
     */
    public function flush(int $timeout = 10000)
    {
        $this->producer->flush($timeout);
    }
}