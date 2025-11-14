<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\User;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class TicketAnalyticsController extends Controller
{
    /**
     * Display analytics page
     */
    public function index(Request $request)
    {
        // Check if user can manage tickets (admin/super admin)
        $canManage = auth()->user()->can('tickets.ticket.manage');

        // Get all users for assignee filter (admin only)
        $allUsers = $canManage 
            ? User::select('id', 'first_name', 'last_name', 'username')
                ->get()
                ->map(function($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name, // Uses the accessor
                    ];
                })
                ->sortBy('name')
                ->values()
            : collect();

        // Get all tags for filtering
        $allTags = Tag::orderBy('name')->get()->pluck('name');

        // Get all unique statuses
        $allStatuses = ['Received', 'Rejected', 'Completed'];

        return Inertia::render('Tickets/Analytics', [
            'canManage' => $canManage,
            'allUsers' => $allUsers,
            'allTags' => $allTags,
            'allStatuses' => $allStatuses,
            'filters' => $request->only(['date_from', 'date_to', 'status', 'tags', 'assignee']),
        ]);
    }

    /**
     * Get analytics data via API
     */
    public function data(Request $request)
    {
        $canManage = auth()->user()->can('tickets.ticket.manage');
        
        // Default to last 30 days if no date range specified
        $dateFrom = $request->input('date_from', now()->subDays(30)->format('Y-m-d'));
        $dateTo = $request->input('date_to', now()->format('Y-m-d'));

        // Base query
        $query = Ticket::with(['user', 'assignees', 'tags']);

        // Apply role-based filtering
        if (!$canManage) {
            // Regular users: only their own tickets or assigned tickets
            $query->where(function($q) {
                $q->where('user_id', auth()->id())
                  ->orWhereHas('assignees', function($sub) {
                      $sub->where('users.id', auth()->id());
                  });
            });
        }

        // Apply date range filter (creation date)
        $query->whereBetween('created_at', [
            Carbon::parse($dateFrom)->startOfDay(),
            Carbon::parse($dateTo)->endOfDay()
        ]);

        // Apply status filter
        if ($request->filled('status')) {
            $statuses = is_array($request->status) 
                ? $request->status 
                : explode(',', $request->status);
            $query->whereIn('status', $statuses);
        }

        // Apply tag filter
        if ($request->filled('tags')) {
            $tagNames = is_array($request->tags)
                ? $request->tags
                : explode(',', $request->tags);
            
            if (!empty($tagNames)) {
                $query->whereHas('tags', function($sub) use ($tagNames) {
                    $sub->whereIn('tags.name', $tagNames);
                });
            }
        }

        // Apply assignee filter (admin only)
        if ($canManage && $request->filled('assignee')) {
            $assigneeIds = is_array($request->assignee)
                ? $request->assignee
                : explode(',', $request->assignee);
            
            if (!empty($assigneeIds)) {
                $query->whereHas('assignees', function($sub) use ($assigneeIds) {
                    $sub->whereIn('users.id', $assigneeIds);
                });
            }
        }

        // Get all tickets matching filters
        $tickets = $query->get();

        // Calculate metrics
        $metrics = $this->calculateMetrics($tickets, $canManage);

        // Get detailed ticket data for table view
        $ticketDetails = $this->getTicketDetails($tickets);

        return response()->json([
            'success' => true,
            'data' => [
                'metrics' => $metrics,
                'tickets' => $ticketDetails,
            ]
        ]);
    }

    /**
     * Calculate analytics metrics
     */
    private function calculateMetrics($tickets, $canManage)
    {
        $now = now();
        
        // Basic counts
        $newTickets = $tickets->where('status', 'Received')->count();
        $completedTickets = $tickets->where('status', 'Completed')->count();
        $rejectedTickets = $tickets->where('status', 'Rejected')->count();
        $totalTickets = $tickets->count();

        // Calculate average time spent
        $timeSpentData = $tickets->map(function($ticket) use ($now) {
            $createdAt = Carbon::parse($ticket->created_at);
            
            // If completed, use completion time; otherwise use current time
            if ($ticket->status === 'Completed' && $ticket->updated_at) {
                $endTime = Carbon::parse($ticket->updated_at);
            } else {
                $endTime = $now;
            }
            
            $diffInMinutes = $createdAt->diffInMinutes($endTime);
            
            return [
                'ticket_id' => $ticket->id,
                'minutes' => $diffInMinutes,
                'formatted' => $this->formatTimeSpent($diffInMinutes),
            ];
        });

        $avgTimeSpentMinutes = $timeSpentData->avg('minutes') ?? 0;
        $avgTimeSpentFormatted = $this->formatTimeSpent($avgTimeSpentMinutes);

        // Status breakdown
        $statusBreakdown = [
            'Received' => $newTickets,
            'Completed' => $completedTickets,
            'Rejected' => $rejectedTickets,
        ];

        // Tickets over time (by day)
        $ticketsOverTime = $tickets->groupBy(function($ticket) {
            return Carbon::parse($ticket->created_at)->format('Y-m-d');
        })->map(function($group) {
            return $group->count();
        })->sortKeys();

        // Tag distribution
        $tagDistribution = [];
        foreach ($tickets as $ticket) {
            foreach ($ticket->tags as $tag) {
                if (!isset($tagDistribution[$tag->name])) {
                    $tagDistribution[$tag->name] = 0;
                }
                $tagDistribution[$tag->name]++;
            }
        }
        arsort($tagDistribution);

        // Assignee performance (admin only)
        $assigneePerformance = [];
        if ($canManage) {
            $assigneeStats = [];
            foreach ($tickets as $ticket) {
                foreach ($ticket->assignees as $assignee) {
                    if (!isset($assigneeStats[$assignee->id])) {
                        $assigneeStats[$assignee->id] = [
                            'name' => $assignee->name,
                            'total' => 0,
                            'completed' => 0,
                        ];
                    }
                    $assigneeStats[$assignee->id]['total']++;
                    if ($ticket->status === 'Completed') {
                        $assigneeStats[$assignee->id]['completed']++;
                    }
                }
            }
            $assigneePerformance = array_values($assigneeStats);
        }

        // Total users count (admin only)
        $totalUsers = $canManage ? User::count() : null;

        return [
            'new_tickets' => $newTickets,
            'completed_tickets' => $completedTickets,
            'rejected_tickets' => $rejectedTickets,
            'total_tickets' => $totalTickets,
            'avg_time_spent' => $avgTimeSpentFormatted,
            'avg_time_spent_minutes' => round($avgTimeSpentMinutes, 2),
            'status_breakdown' => $statusBreakdown,
            'tickets_over_time' => $ticketsOverTime,
            'tag_distribution' => $tagDistribution,
            'assignee_performance' => $assigneePerformance,
            'total_users' => $totalUsers,
        ];
    }

    /**
     * Get detailed ticket information for table view
     */
    private function getTicketDetails($tickets)
    {
        $now = now();
        
        return $tickets->map(function($ticket) use ($now) {
            $createdAt = Carbon::parse($ticket->created_at);
            
            // Calculate time spent
            if ($ticket->status === 'Completed' && $ticket->updated_at) {
                $endTime = Carbon::parse($ticket->updated_at);
                $completedAt = $ticket->updated_at;
            } else {
                $endTime = $now;
                $completedAt = null;
            }
            
            $diffInMinutes = $createdAt->diffInMinutes($endTime);
            
            return [
                'id' => $ticket->id,
                'title' => $ticket->title,
                'status' => $ticket->status,
                'created_at' => $ticket->created_at,
                'completed_at' => $completedAt,
                'time_spent' => $this->formatTimeSpent($diffInMinutes),
                'time_spent_minutes' => $diffInMinutes,
                'submitter' => $ticket->user ? $ticket->user->name : 'Unknown',
                'assignees' => $ticket->assignees->pluck('name')->join(', '),
                'tags' => $ticket->tags->pluck('name')->toArray(),
            ];
        })->values();
    }

    /**
     * Format time spent in "X days Y hours" format
     */
    private function formatTimeSpent($minutes)
    {
        if ($minutes < 1) {
            return '0 minutes';
        }

        $days = floor($minutes / 1440); // 1440 minutes in a day
        $hours = floor(($minutes % 1440) / 60);
        $mins = $minutes % 60;

        $parts = [];
        
        if ($days > 0) {
            $parts[] = $days . ' ' . ($days === 1 ? 'day' : 'days');
        }
        
        if ($hours > 0) {
            $parts[] = $hours . ' ' . ($hours === 1 ? 'hour' : 'hours');
        }
        
        // Only show minutes if less than 1 day
        if ($days === 0 && $mins > 0) {
            $parts[] = round($mins) . ' ' . (round($mins) === 1 ? 'minute' : 'minutes');
        }

        return implode(' ', $parts) ?: '0 minutes';
    }
}
