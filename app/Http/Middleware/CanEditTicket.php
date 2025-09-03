<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Ticket;

class CanEditTicket
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

        // Check if user has ticket update permission
        if ($user->can('tickets.ticket.update')) {
            return $next($request);
        }

        // Check if user is editing their own ticket
        $ticket = $request->route('ticket');
        if ($ticket && $ticket->user_id === $user->id) {
            return $next($request);
        }

        abort(403, 'You can only edit your own tickets.');
    }
}
