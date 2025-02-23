<?php

namespace App\Providers;

use App\Services\MediaService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use getID3;
use Illuminate\Contracts\Support\DeferrableProvider;

class MediaServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * All of the container singletons that should be registered.
     *
     * @var array
     */
    public $singletons = [
        MediaService::class => MediaService::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MediaService::class, function (Application $app) {
            return new MediaService(
                new getID3()
            );
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [MediaService::class];
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
