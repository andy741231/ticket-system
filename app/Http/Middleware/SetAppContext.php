<?php

namespace App\Http\Middleware;

use App\Models\App as SubApp;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;

class SetAppContext
{
    public function handle(Request $request, Closure $next)
    {
        $path = trim($request->path(), '/');
        $segments = $path === '' ? [] : explode('/', $path);
        $prefix = $segments[0] ?? null;
        // If hitting API routes like /api/{app}/..., use the second segment as app slug
        if ($prefix === 'api' && isset($segments[1])) {
            $prefix = $segments[1];
        }

        $app = null;
        // Map legacy/front-end 'users' URL segment to Hub app slug
        $lookupSlug = $prefix;
        if ($lookupSlug === 'users') {
            $lookupSlug = 'hub';
        }
        if ($lookupSlug) {
            $app = SubApp::query()->where('slug', $lookupSlug)->first();
        }

        $teamId = $app?->id;

        // Share in request attributes
        $request->attributes->set('current_app', $app);
        $request->attributes->set('current_team_id', $teamId);

        // Set Spatie team context for this request
        /** @var PermissionRegistrar $registrar */
        $registrar = app(PermissionRegistrar::class);
        $prev = method_exists($registrar, 'getPermissionsTeamId') ? ($registrar->getPermissionsTeamId()) : null;
        $registrar->setPermissionsTeamId($teamId);

        try {
            $response = $next($request);
        } finally {
            // Restore previous team context
            $registrar->setPermissionsTeamId($prev);
        }

        return $response;
    }
}
