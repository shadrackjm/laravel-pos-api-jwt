<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'jwt.auth' => \App\Http\Middleware\EnsureTokenIsValid::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Episode 7 — one error shape for the whole API.
        //
        // Without this, a validation failure, a missing record and an uncaught
        // bug each come back in a different format, and every client has to
        // special-case all three.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null; // let web routes render normally
            }

            return match (true) {
                $e instanceof ValidationException => response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], 422),

                $e instanceof AuthenticationException => response()->json([
                    'success' => false,
                    'message' => 'Authentication token not provided.',
                ], 401),

                // Covers route-model binding misses. Note the generic wording:
                // "Sale 12 not found" tells an attacker sale 12 does not exist,
                // which is a slow way to map your whole database.
                $e instanceof ModelNotFoundException,
                $e instanceof NotFoundHttpException => response()->json([
                    'success' => false,
                    'message' => 'Resource not found.',
                ], 404),

                $e instanceof MethodNotAllowedHttpException => response()->json([
                    'success' => false,
                    'message' => 'This method is not allowed for this endpoint.',
                ], 405),

                $e instanceof ThrottleRequestsException => response()->json([
                    'success' => false,
                    'message' => 'Too many requests. Please slow down.',
                ], 429),

                $e instanceof HttpExceptionInterface => response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'Request failed.',
                ], $e->getStatusCode()),

                // Anything unplanned. In production we must NOT leak the
                // exception message — it routinely contains SQL, file paths and
                // occasionally credentials. Locally we want to see it.
                default => response()->json([
                    'success' => false,
                    'message' => app()->hasDebugModeEnabled()
                        ? $e->getMessage()
                        : 'Something went wrong on our end.',
                ], 500),
            };
        });
    })->create();
