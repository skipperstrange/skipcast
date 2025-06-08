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
        // Get the current server roles
        $roles = config('server.roles', ['all']);
        
        // Collect all services for these roles
        $services = collect();
        foreach ($roles as $role) {
            $roleServices = config("server.available_roles.{$role}.services", []);
            $services = $services->merge($roleServices);
        }
        
        // Register unique services for these roles
        foreach ($services->unique() as $service) {
            $this->app->singleton($service, function ($app) use ($service) {
                return new $service();
            });
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
    }
}
