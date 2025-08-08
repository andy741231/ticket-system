<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $now = now();
        $lastMonth = $now->copy()->subMonth();
        
        // Get ticket counts - using the actual status values from the database
        $openTickets = Ticket::where('status', 'Received')->count();
        $inProgressTickets = Ticket::whereIn('status', ['Approved'])->count();
        $totalUsers = User::count();
        
        // Calculate open tickets change from last month
        $previousMonthOpenTickets = Ticket::where('status', 'Received')
            ->where('created_at', '<=', $lastMonth->endOfMonth())
            ->count();
            
        $openTicketsChange = $previousMonthOpenTickets > 0 
            ? (($openTickets - $previousMonthOpenTickets) / $previousMonthOpenTickets) * 100 
            : 0;

        $stats = [
            'open_tickets' => $openTickets,
            'open_tickets_change' => round($openTicketsChange, 1),
            'in_progress_tickets' => $inProgressTickets,
            'total_users' => $totalUsers,
        ];
        
        // Log the stats for debugging
        \Log::info('Dashboard stats being sent to view:', $stats);
        
        // Log the stats being passed to the view
        \Log::info('Dashboard stats:', $stats);
        
        // Pass the stats to the view
        $data = [
            'stats' => $stats
        ];
        
        // Log the data being passed to Inertia
        \Log::info('Data being passed to Inertia:', $data);
        
        $response = Inertia::render('Dashboard', $data);
        
        \Log::info('Inertia response data:', $response->getData());
        
        return $response;
    }
    
    /**
     * Return raw stats data for debugging
     */
    public function getStats()
    {
        $now = now();
        $lastMonth = $now->copy()->subMonth();
        
        // Get ticket counts - using the actual status values from the database
        $openTickets = Ticket::where('status', 'Received')->count();
        $inProgressTickets = Ticket::whereIn('status', ['Approved'])->count();
        $totalUsers = User::count();
        
        // Calculate open tickets change from last month
        $previousMonthOpenTickets = Ticket::where('status', 'Received')
            ->where('created_at', '<=', $lastMonth->endOfMonth())
            ->count();
            
        $openTicketsChange = $previousMonthOpenTickets > 0 
            ? (($openTickets - $previousMonthOpenTickets) / $previousMonthOpenTickets) * 100 
            : 0;

        return response()->json([
            'stats' => [
                'open_tickets' => $openTickets,
                'open_tickets_change' => round($openTicketsChange, 1),
                'in_progress_tickets' => $inProgressTickets,
                'total_users' => $totalUsers,
            ]
        ]);
    }
}
