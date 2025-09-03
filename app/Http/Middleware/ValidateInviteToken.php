<?php

namespace App\Http\Middleware;

use App\Models\Invite;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateInviteToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->route('token');
        
        if (!$token) {
            abort(404, 'Invitation token not found.');
        }

        $invite = Invite::where('token', $token)->first();

        if (!$invite) {
            abort(404, 'Invalid invitation token.');
        }

        if (!$invite->isValid()) {
            if ($invite->isAccepted()) {
                abort(410, 'This invitation has already been accepted.');
            }
            
            if ($invite->isExpired()) {
                abort(410, 'This invitation has expired.');
            }
        }

        // Add invite to request for use in controller
        $request->merge(['invite' => $invite]);

        return $next($request);
    }
}
