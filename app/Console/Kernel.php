<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $hasRole = function($role) {
            return config('server.has_role')($role);
        };
        
        // Commands for metrics role
        if ($hasRole('metrics')) {
            $schedule->command('icecast:metrics')
                    ->everyMinute()
                    ->withoutOverlapping()
                    ->appendOutputTo(storage_path('logs/icecast-metrics.log'));
        }
        
        // Commands for API role
        if ($hasRole('api')) {
            $schedule->command('kafka:consume')
                    ->everyMinute()
                    ->withoutOverlapping(1440) // 24 hours
                    ->runInBackground()
                    ->appendOutputTo(storage_path('logs/kafka-consumer.log'));
        }
        
        // Commands for Liquidsoap role
        if ($hasRole('liquidsoap')) {
            $schedule->command('liquidsoap:command-consumer')
                    ->everyMinute()
                    ->withoutOverlapping(1440) // 24 hours
                    ->runInBackground()
                    ->appendOutputTo(storage_path('logs/liquidsoap-command-consumer.log'));
        }
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $roles = config('server.roles', ['all']);
        $commands = collect();
        
        // Collect commands for all active roles
        foreach ($roles as $role) {
            $roleCommands = config("server.available_roles.{$role}.commands", []);
            $commands = $commands->merge($roleCommands);
        }
        
        // Register unique commands
        $this->load(__DIR__.'/Commands');
        
        // Set the $commands property dynamically
        foreach ($commands->unique() as $command) {
            if (class_exists($command)) {
                $this->commands[] = $command;
            }
        }

        require base_path('routes/console.php');
    }
}
