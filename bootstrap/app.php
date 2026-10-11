<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Consistent error envelope for validation errors.
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'error' => [
                        'code'    => 'VALIDATION_ERROR',
                        'message' => 'Periksa kembali data yang dikirim.',
                        'fields'  => $e->errors(),
                    ],
                ], 422);
            }
        });

        // Consistent envelope for HTTP exceptions (403, 404, etc.).
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'error' => [
                        'code'    => match ($e->getStatusCode()) {
                            401 => 'UNAUTHENTICATED',
                            403 => 'FORBIDDEN',
                            404 => 'NOT_FOUND',
                            429 => 'TOO_MANY_REQUESTS',
                            default => 'HTTP_ERROR',
                        },
                        'message' => $e->getMessage() ?: 'Terjadi kesalahan.',
                    ],
                ], $e->getStatusCode());
            }
        });
    })->create();
