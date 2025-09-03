<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Team;

class CanEditDirectoryProfile
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if (!$user) {
            abort(401);
        }

        // Check if user has directory management permission
        if ($user->can('directory.profile.manage')) {
            return $next($request);
        }

        // Check if user is editing their own profile
        $team = $request->route('team');
        if ($team && $team->email === $user->email) {
            return $next($request);
        }

        abort(403, 'You can only edit your own directory profile.');
    }
}
