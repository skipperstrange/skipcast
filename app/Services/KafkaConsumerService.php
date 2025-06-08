<?php

namespace App\Services;

use RdKafka\Conf;
use RdKafka\KafkaConsumer;
use RdKafka\Message;
use Illuminate\Support\Facades\Log;

class KafkaConsumerService
{
    protected $consumer;
    protected $handlers = [];

    public function __construct()
    {
        $this->initConsumer();
    }

    /**
     * Initialize the Kafka consumer
     */
    private function initConsumer()
    {
        try {
            $conf = new Conf();
            $conf->set('metadata.broker.list', config('kafka.broker'));
            $conf->set('group.id', config('kafka.consumer_group'));
            $conf->set('auto.offset.reset', 'earliest');
            $conf->set('enable.auto.commit', 'false');

            $this->consumer = new KafkaConsumer($conf);
        } catch (\Exception $e) {
            Log::error('Failed to initialize Kafka consumer: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Register a handler for a specific event type
     */
    public function registerHandler(string $eventType, callable $handler)
    {
        $this->handlers[$eventType] = $handler;
    }

    /**
     * Start consuming messages from specified topics
     */
    public function consume(array $topics, int $timeout = 120000)
    {
        try {
            // Subscribe to topics
            $this->consumer->subscribe($topics);

            while (true) {
                $message = $this->consumer->consume($timeout);

                switch ($message->err) {
                    case RD_KAFKA_RESP_ERR_NO_ERROR:
                        $this->handleMessage($message);
                        break;

                    case RD_KAFKA_RESP_ERR__PARTITION_EOF:
                        Log::info('No more messages; will wait for more');
                        break;

                    case RD_KAFKA_RESP_ERR__TIMED_OUT:
                        Log::info('Timed out');
                        break;

                    default:
                        Log::error('Kafka error: ' . $message->errstr());
                        throw new \Exception($message->errstr(), $message->err);
                }

                // Commit the offset
                $this->consumer->commit($message);
            }
        } catch (\Exception $e) {
            Log::error('Error consuming Kafka messages', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Handle a received message
     */
    protected function handleMessage(Message $message)
    {
        try {
            $data = json_decode($message->payload, true);

            if (!isset($data['event'])) {
                Log::warning('Received message without event type', ['data' => $data]);
                return;
            }

            $eventType = $data['event'];

            if (isset($this->handlers[$eventType])) {
                call_user_func($this->handlers[$eventType], $data);
            } else {
                Log::warning("No handler registered for event type: {$eventType}");
            }

            if (config('kafka.debug')) {
                Log::debug('Processed Kafka message', [
                    'topic' => $message->topic_name,
                    'partition' => $message->partition,
                    'offset' => $message->offset,
                    'event_type' => $eventType,
                    'data' => $data
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to handle Kafka message', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'topic' => $message->topic_name,
                'partition' => $message->partition,
                'offset' => $message->offset
            ]);
            throw $e;
        }
    }
}
