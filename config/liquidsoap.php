<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Liquidsoap Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration settings for Liquidsoap streaming.
    |
    */

    // Server settings
    'host' => env('LIQUIDSOAP_HOST', 'localhost'),
    'port' => env('LIQUIDSOAP_PORT', 8000),
    'password' => env('LIQUIDSOAP_PASSWORD', 'hackme'),

    // Logging settings
    'log_path' => env('LIQUIDSOAP_LOG_PATH', '/var/log/liquidsoap'),
    'log_level' => env('LIQUIDSOAP_LOG_LEVEL', 3),

    // Audio settings
    'bitrate' => env('LIQUIDSOAP_BITRATE', 128),
    'samplerate' => env('LIQUIDSOAP_SAMPLERATE', 44100),
    'stereo' => env('LIQUIDSOAP_STEREO', true),

    // File paths
    'config_path' => env('LIQUIDSOAP_CONFIG_PATH', storage_path('app/liquidsoap')),
    'playlist_path' => env('LIQUIDSOAP_PLAYLIST_PATH', storage_path('app/playlists')),
    'playlist_storage_path' => storage_path('app/playlists'),
    'media_path' => env('LIQUIDSOAP_MEDIA_PATH', storage_path('app')),
    'log_path' => storage_path('logs/liquidsoap'),
    
    /*
    |--------------------------------------------------------------------------
    | SSH Configuration for Remote Liquidsoap Control
    |--------------------------------------------------------------------------
    |
    | These settings are used when controlling Liquidsoap on a remote server.
    |
    */
    'ssh_user' => env('LIQUIDSOAP_SSH_USER', 'liquidsoap'),
    'ssh_key_path' => env('LIQUIDSOAP_SSH_KEY_PATH', storage_path('ssh/liquidsoap_rsa')),
    
];
