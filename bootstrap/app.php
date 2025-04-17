<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use App\Models\Lottery;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders()
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Custom reporting for specific exceptions
        $exceptions->report(function (ModelNotFoundException $e) {
            // Log the exception or send it to an external service
        });

        // Custom rendering for ModelNotFoundException
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            return response()->json([
                'error' => 'Resource not found',
                'message' => 'The requested resource does not exist'
            ], 404);
        });

        // Add global context
        $exceptions->context(fn () => [
            'user_id' => auth()->id(),
        ]);

        // Throttle reported exceptions
        $exceptions->throttle(function (Throwable $e) {
            return Lottery::odds(1, 1000); // Report 1 in 1000
        });
    })->create();
