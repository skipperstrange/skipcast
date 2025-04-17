<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

trait HandlesApiResponses
{
    /**
     * Convert an authentication exception into a response.
     */
    protected function handleModelNotFound($exception)
    {
        $modelName = class_basename($exception->getModel());
        
        return response()->json([
            'error' => "{$modelName} not found",
            'message' => "The requested {$modelName} does not exist"
        ], 404);
    }
} 