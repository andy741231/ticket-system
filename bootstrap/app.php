<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        // Process scheduled sends every minute
        $schedule->command('campaigns:process-scheduled-sends', ['--limit' => 100])
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/scheduler.log'));

        // Process recurring campaigns every 15 minutes
        $schedule->command('campaigns:process-recurring')
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        // Heartbeat to verify the scheduler loop is alive
        $schedule->call(function () {
            \Log::info('[Scheduler] heartbeat');
        })
            ->name('scheduler:heartbeat')
            ->everyMinute()
            ->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust proxy/load balancer headers (including X-Forwarded-Proto) so HTTPS is respected
        $middleware->trustProxies(
            at: '*',
            headers: HttpRequest::HEADER_X_FORWARDED_FOR
                | HttpRequest::HEADER_X_FORWARDED_HOST
                | HttpRequest::HEADER_X_FORWARDED_PROTO
                | HttpRequest::HEADER_X_FORWARDED_PORT
        );

        $middleware->web(append: [
            \App\Http\Middleware\SetAppContext::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Register role-based middleware
        $middleware->alias([
            'role.redirect' => \App\Http\Middleware\RedirectBasedOnRole::class,
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'perm' => \App\Http\Middleware\RequirePermission::class,
            'can.edit.directory' => \App\Http\Middleware\CanEditDirectoryProfile::class,
            'can.edit.ticket' => \App\Http\Middleware\CanEditTicket::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $e, $request) {
            // Helper function to get error data based on exception type
            $getErrorData = function ($exception) {
                // Authentication errors
                if ($exception instanceof AuthenticationException) {
                    return [
                        'type' => 'authentication',
                        'message' => 'Authentication required. Please log in to continue.',
                        'status' => 401,
                    ];
                }

                // Authorization errors
                if ($exception instanceof AuthorizationException) {
                    return [
                        'type' => 'authorization',
                        'message' => 'You do not have permission to perform this action.',
                        'status' => 403,
                    ];
                }

                // Validation errors
                if ($exception instanceof ValidationException) {
                    return [
                        'type' => 'validation',
                        'message' => 'The provided data is invalid.',
                        'status' => 422,
                        'details' => $exception->errors(),
                    ];
                }

                // Database errors
                if ($exception instanceof QueryException) {
                    $message = 'A database error occurred.';
                    
                    // Provide more specific messages for common database errors
                    if (str_contains($exception->getMessage(), 'Duplicate entry')) {
                        $message = 'This record already exists.';
                    } elseif (str_contains($exception->getMessage(), 'foreign key constraint')) {
                        $message = 'Cannot complete action due to related data constraints.';
                    } elseif (str_contains($exception->getMessage(), 'Connection refused')) {
                        $message = 'Database connection failed. Please try again later.';
                    }

                    return [
                        'type' => 'database',
                        'message' => $message,
                        'status' => 500,
                    ];
                }

                // Model not found errors
                if ($exception instanceof ModelNotFoundException) {
                    return [
                        'type' => 'not_found',
                        'message' => 'The requested resource was not found.',
                        'status' => 404,
                    ];
                }

                // HTTP Not Found errors
                if ($exception instanceof NotFoundHttpException) {
                    return [
                        'type' => 'not_found',
                        'message' => 'The requested page or resource was not found.',
                        'status' => 404,
                    ];
                }

                // General HTTP exceptions
                if ($exception instanceof HttpException) {
                    return [
                        'type' => 'http',
                        'message' => $exception->getMessage() ?: 'An HTTP error occurred.',
                        'status' => $exception->getStatusCode(),
                    ];
                }

                // Rate limiting errors
                if ($exception instanceof \Illuminate\Http\Exceptions\ThrottleRequestsException) {
                    return [
                        'type' => 'rate_limit',
                        'message' => 'Too many requests. Please slow down and try again later.',
                        'status' => 429,
                    ];
                }

                // File/Storage errors
                if ($exception instanceof \Illuminate\Contracts\Filesystem\FileNotFoundException) {
                    return [
                        'type' => 'file_not_found',
                        'message' => 'The requested file was not found.',
                        'status' => 404,
                    ];
                }

                // Mail errors
                if (str_contains(get_class($exception), 'Mail') || str_contains($exception->getMessage(), 'mail')) {
                    return [
                        'type' => 'mail',
                        'message' => 'Failed to send email. Please try again later.',
                        'status' => 500,
                    ];
                }

                // Default fallback for other exceptions
                return [
                    'type' => 'general',
                    'message' => app()->environment('production') 
                        ? 'An unexpected error occurred. Please try again later.'
                        : ($exception->getMessage() ?: 'An error occurred'),
                    'status' => method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 500,
                ];
            };

            // Determine error details based on exception type
            $errorData = $getErrorData($e);

            // For Inertia requests, handle them first before JSON
            if ($request->header('X-Inertia')) {
                // Handle authentication errors - redirect to login
                if ($errorData['type'] === 'authentication') {
                    return redirect()->guest(route('login'));
                }
                
                // Handle validation errors specially for Inertia
                if ($errorData['type'] === 'validation' && isset($errorData['details'])) {
                    return back()->withErrors($errorData['details'])->withInput();
                }
                
                // For other errors, use flash messages
                $flashType = $errorData['status'] >= 500 ? 'error' : 'warning';
                return back()->with($flashType, $errorData['message']);
            }

            // Handle API routes and pure JSON requests (not Inertia)
            if ($request->is('api/*') || ($request->expectsJson() && !$request->header('X-Inertia'))) {
                return response()->json([
                    'message' => $errorData['message'],
                    'error' => true,
                    'type' => $errorData['type'],
                    'status' => $errorData['status'],
                    'details' => $errorData['details'] ?? null,
                ], $errorData['status']);
            }

            return null; // Let Laravel handle other cases normally
        });
    })->create();
