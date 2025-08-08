<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class TicketController extends Controller
{
    use AuthorizesRequests;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Ticket::class);

        $tickets = Ticket::with('user')
            ->when(!auth()->user()->hasRole('admin'), function ($query) {
                return $query->where('user_id', auth()->id());
            })
            ->when($request->search, function ($query, $search) {
                $query->where(function($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($request->status, function ($query, $status) {
                $query->whereIn('status', explode(',', $status));
            })
            ->when($request->priority, function ($query, $priority) {
                $query->where('priority', $priority);
            })
            ->when($request->assignee, function ($query, $assignee) {
                $query->where('assigned_to', $assignee);
            })
            ->when($request->date_from, function ($query, $date) {
                $query->whereDate('created_at', '>=', $date);
            })
            ->when($request->date_to, function ($query, $date) {
                $query->whereDate('created_at', '<=', $date);
            })
            ->orderBy($request->input('sort_field', 'created_at'), $request->input('sort_direction', 'desc'))
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Tickets/Index', [
            'tickets' => $tickets,
            'filters' => $request->only(['search', 'status', 'priority', 'assignee', 'date_from', 'date_to', 'sort_field', 'sort_direction']),
            'status' => session('status'),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', Ticket::class);

        return Inertia::render('Tickets/Create', [
            'priorities' => ['Low', 'Medium', 'High'],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Ticket::class);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'required|in:Low,Medium,High',
        ]);

        $ticket = new Ticket($validated);
        $ticket->user_id = auth()->id();
        $ticket->status = 'Received';
        $ticket->save();

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('status', 'Ticket created successfully!');
    }

    /**
     * Display the specified resource.
     */
    public function show(Ticket $ticket)
    {
        $this->authorize('view', $ticket);

        $ticket->load(['user', 'files']);
        
        return Inertia::render('Tickets/Show', [
            'ticket' => array_merge($ticket->toArray(), [
                'files' => $ticket->files
            ]),
            'can' => [
                'update' => auth()->user()->can('update', $ticket),
                'delete' => auth()->user()->can('delete', $ticket),
                'changeStatus' => auth()->user()->can('changeStatus', $ticket),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Ticket $ticket)
    {
        $this->authorize('update', $ticket);
        
        // Load the files relationship
        $ticket->load('files');

        return Inertia::render('Tickets/Edit', [
            'ticket' => $ticket,
            'priorities' => ['Low', 'Medium', 'High'],
            'statuses' => ['Received', 'Approved', 'Rejected', 'Completed'],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Ticket $ticket)
    {
        $this->authorize('update', $ticket);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'required|in:Low,Medium,High',
            'status' => 'sometimes|required|in:Received,Approved,Rejected,Completed',
        ]);

        $ticket->update($validated);

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('status', 'Ticket updated successfully!');
    }

    /**
     * Update the status of the specified resource.
     */
    public function updateStatus(Request $request, Ticket $ticket)
    {
        $this->authorize('changeStatus', $ticket);

        $validated = $request->validate([
            'status' => 'required|in:Approved,Rejected,Completed',
        ]);

        $ticket->update($validated);

        return redirect()
            ->back()
            ->with('status', 'Ticket status updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Ticket $ticket)
    {
        $this->authorize('delete', $ticket);

        $ticket->delete();

        return redirect()
            ->route('tickets.index')
            ->with('status', 'Ticket deleted successfully!');
    }
}
