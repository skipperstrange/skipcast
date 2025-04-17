<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use App\Services\GenreService;
use App\Services\ChannelMediaService;
use App\Services\MediaService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register the GenreService as a singleton
        $this->app->singleton(GenreService::class, function ($app) {
            return new GenreService();
        });

        // Register the ChannelMediaService as a singleton
        $this->app->singleton(ChannelMediaService::class, function ($app) {
            return new ChannelMediaService();
        });

        // Register the MediaService as a singleton
        $this->app->singleton(MediaService::class, function ($app) {
            return new MediaService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
    }
}
