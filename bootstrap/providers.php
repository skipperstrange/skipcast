<?php

return [
    // Laravel Framework Service Providers...
    Illuminate\Auth\AuthServiceProvider::class,
    // ... other framework providers ...

    // Package Service Providers...
    ProtoneMedia\LaravelFFMpeg\Support\ServiceProvider::class,

    // Application Service Providers...
    App\Providers\AppServiceProvider::class,
    App\Providers\MediaServiceProvider::class,
];
