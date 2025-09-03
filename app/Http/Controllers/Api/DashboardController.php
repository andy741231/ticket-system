<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware(['web', 'auth']);
    }

    /**
     * Return high-level dashboard statistics.
     */
    public function stats(Request $request)
    {
        // Compute stats
        $openTickets = Ticket::where('status', 'Received')->count();
        // UI label says "Tickets approved" though the variable is in_progress_tickets
        $approvedTickets = Ticket::where('status', 'Approved')->count();
        $totalUsers = User::count();

        // Optionally compute percentage change placeholders (0 for now)
        $openTicketsChange = 0;

        return response()->json([
            'stats' => [
                'open_tickets' => $openTickets,
                'open_tickets_change' => $openTicketsChange,
                'in_progress_tickets' => $approvedTickets,
                'total_users' => $totalUsers,
            ],
        ]);
    }
}
