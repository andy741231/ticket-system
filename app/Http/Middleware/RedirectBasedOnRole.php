<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class RedirectBasedOnRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                $user = Auth::guard($guard)->user();
                
                // Check if the user is super admin or has Hub user management permission
                if ((method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) || $user->can('hub.user.manage')) {
                    return redirect()->route('admin.dashboard');
                }
                
                // For all other roles (including 'user'), redirect to tickets index
                return redirect()->route('tickets.index');
            }
        }

        return $next($request);
    }
}
