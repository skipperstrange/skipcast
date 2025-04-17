<?php

namespace App\Providers;

use App\Services\ChannelMediaService;
use Illuminate\Support\ServiceProvider;

class ChannelMediaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ChannelMediaService::class, function ($app) {
            return new ChannelMediaService();
        });
    }
} 