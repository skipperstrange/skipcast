<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Kafka Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration settings for Apache Kafka integration.
    |
    */

    // Kafka broker connection settings
    'broker' => env('KAFKA_BROKER', '172.24.49.76:9092'),

    // Topics for different event types
    'topics' => [
        'stream_events' => env('KAFKA_TOPIC_STREAM_EVENTS', 'skipcast-stream-events'),
        'listener_metrics' => env('KAFKA_TOPIC_LISTENER_METRICS', 'skipcast-listener-metrics'),
        'user_interactions' => env('KAFKA_TOPIC_USER_INTERACTIONS', 'skipcast-user-interactions'),
    ],

    // Consumer group settings
    'consumer_group' => env('KAFKA_CONSUMER_GROUP', 'skipcast-consumer-group'),

    // Additional settings
    'debug' => env('KAFKA_DEBUG', false),
    'timeout' => env('KAFKA_TIMEOUT', 120000), // 2 minutes in milliseconds
];
