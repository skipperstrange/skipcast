<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Server Roles Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration determines which components of the application are
    | active on this server instance. A server can have multiple roles.
    |
    */

    'roles' => array_map('trim', explode(',', env('SERVER_ROLES', 'all'))),
    
    'available_roles' => [
        'api' => [
            'description' => 'API server handling user requests',
            'services' => [
                App\Services\StreamService::class,
                App\Services\ChannelMediaService::class,
                App\Services\GenreService::class,
                App\Services\MediaService::class,
            ],
            'commands' => [
                App\Console\Commands\ConsumeKafkaMessages::class,
            ],
        ],
        
        'liquidsoap' => [
            'description' => 'Liquidsoap server handling audio streaming',
            'services' => [
                App\Services\LiquidsoapCommandService::class,
            ],
            'commands' => [
                App\Console\Commands\LiquidsoapCommandConsumer::class,
            ],
        ],
        
        'icecast' => [
            'description' => 'Icecast server for streaming distribution',
            'services' => [],
            'commands' => [],
        ],
        
        'metrics' => [
            'description' => 'Metrics collection for Icecast statistics',
            'services' => [
                App\Services\IcecastMetricsService::class,
            ],
            'commands' => [
                App\Console\Commands\FetchIcecastMetrics::class,
            ],
        ],
        
        'kafka' => [
            'description' => 'Kafka message broker',
            'services' => [],
            'commands' => [],
        ],
        
        'all' => [
            'description' => 'All-in-one server (development)',
            'services' => [
                App\Services\StreamService::class,
                App\Services\ChannelMediaService::class,
                App\Services\GenreService::class,
                App\Services\MediaService::class,
                App\Services\LiquidsoapCommandService::class,
                App\Services\IcecastMetricsService::class,
            ],
            'commands' => [
                App\Console\Commands\ConsumeKafkaMessages::class,
                App\Console\Commands\LiquidsoapCommandConsumer::class,
                App\Console\Commands\FetchIcecastMetrics::class,
            ],
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Role Check Helper
    |--------------------------------------------------------------------------
    |
    | Helper function to check if the server has a specific role.
    |
    */
    'has_role' => function(string $role): bool {
        $roles = config('server.roles', ['all']);
        return in_array($role, $roles) || in_array('all', $roles);
    },
];
