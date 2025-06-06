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

    // Mount points
    'public_mount' => env('LIQUIDSOAP_PUBLIC_MOUNT', '/public'),
    'private_mount' => env('LIQUIDSOAP_PRIVATE_MOUNT', '/private'),
]; 