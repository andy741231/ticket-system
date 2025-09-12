<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust proxy/load balancer headers (including X-Forwarded-Proto) so HTTPS is respected
        $middleware->trustProxies(at: '*', headers: SymfonyRequest::HEADER_X_FORWARDED_ALL);

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
        //
    })->create();
