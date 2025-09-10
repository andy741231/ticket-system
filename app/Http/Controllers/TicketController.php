<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TempFile;
use App\Models\TicketFile;
use App\Models\User;
use App\Models\Role;
use App\Models\Team;
use App\Mail\TicketCreated;
use App\Mail\TicketApproved;
use App\Mail\TicketRejected;
use App\Mail\TicketCompleted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Address;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;

class TicketController extends Controller
{
    use AuthorizesRequests;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Ticket::class);
        
        $canManage = auth()->user()->can('tickets.ticket.manage');
        $rawScope = $request->input('scope');
        $scope = in_array($rawScope, ['assigned', 'submitted'], true)
            ? $rawScope
            : null; // default: no explicit scope; we'll apply a base filter for non-managers

        $tickets = Ticket::with(['user', 'assignees'])
            // Base ownership filter for non-managers when no explicit scope is selected:
            // include tickets submitted by me OR assigned to me.
            ->when(!$canManage && $scope === null, function ($query) {
                $query->where(function($q) {
                    $q->where('user_id', auth()->id())
                      ->orWhereHas('assignees', function($sub) {
                          $sub->where('users.id', auth()->id());
                      });
                });
            })
            // Explicit ownership scopes (override the base filter above)
            ->when($scope === 'submitted', function ($query) {
                $query->where('user_id', auth()->id());
            })
            ->when($scope === 'assigned', function ($query) {
                $query->whereHas('assignees', function($sub) {
                    $sub->where('users.id', auth()->id());
                });
            })
            ->when($request->search, function ($query, $search) {
                $query->where(function($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description_text', 'like', "%{$search}%");
                });
            })
            ->when($request->status, function ($query, $status) {
                $query->whereIn('status', explode(',', $status));
            })
            ->when($request->priority, function ($query, $priority) {
                $query->where('priority', $priority);
            })
            ->when($request->assignee, function ($query, $assignee) {
                $ids = collect(explode(',', (string) $assignee))
                    ->filter()
                    ->map(fn($v) => (int) $v)
                    ->unique()
                    ->values()
                    ->all();

                if (!empty($ids)) {
                    $query->whereHas('assignees', function($sub) use ($ids) {
                        $sub->whereIn('users.id', $ids);
                    });
                }
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
            'filters' => array_merge(
                $request->only(['search', 'status', 'priority', 'assignee', 'date_from', 'date_to', 'sort_field', 'sort_direction']),
                ['scope' => $scope]
            ),
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
            'users' => User::all()->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                ];
            }),
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
            'due_date' => 'nullable|date',
            'assigned_user_ids' => 'sometimes|array',
            'assigned_user_ids.*' => 'integer|exists:users,id',
            'temp_file_ids' => 'sometimes|array',
            'temp_file_ids.*' => 'integer',
        ]);

        $ticket = new Ticket($validated);
        $ticket->user_id = auth()->id();
        $ticket->status = 'Received';
        $ticket->updated_by = auth()->id();
        $ticket->save();

        // Sync multi assignees (pivot)
        $assignedIds = collect($request->input('assigned_user_ids', []))
            ->filter()
            ->map(fn($v) => (int) $v)
            ->unique()
            ->values();

        if ($assignedIds->isNotEmpty()) {
            $ticket->assignees()->sync($assignedIds->all());
        }

        // If there are temporary files, move them to the ticket and create TicketFile records
        $tempIds = collect($request->input('temp_file_ids', []))
            ->filter();

        if ($tempIds->isNotEmpty()) {
            // Fetch only the current user's temp files by IDs
            $tempFiles = TempFile::whereIn('id', $tempIds)
                ->where('user_id', auth()->id())
                ->get();

            foreach ($tempFiles as $temp) {
                // Determine new path under tickets/{ticket_id}
                $fileName = basename($temp->file_path);
                $newPath = 'tickets/' . $ticket->id . '/' . $fileName;

                // Move file within public disk
                if (Storage::disk('public')->exists($temp->file_path)) {
                    Storage::disk('public')->move($temp->file_path, $newPath);
                }

                // Create TicketFile record
                TicketFile::create([
                    'ticket_id' => $ticket->id,
                    'file_path' => $newPath,
                    'original_name' => $temp->original_name,
                    'mime_type' => $temp->mime_type,
                    'size' => $temp->size,
                ]);

                // Remove temp file record
                $temp->delete();
            }
        }

        // Notify Tickets app admins with a link to the new ticket (non-blocking if mail fails)
        $mailWarning = null;
        try {
            // Resolve current team/app context from request attributes (set by SetAppContext middleware)
            $teamId = $request->attributes->get('current_team_id');
            if ($teamId) {
                // Find the per-app admin role for the Tickets app (team-scoped)
                $adminRole = Role::where([
                    'slug' => 'admin',
                    'guard_name' => 'web',
                    'team_id' => $teamId,
                ])->first();

                if ($adminRole) {
                    $admins = $adminRole->users()->whereNotNull('email')->get();
                    if ($admins->isNotEmpty()) {
                        $ticketUrl = route('tickets.show', $ticket);
                        $submitterName = auth()->user()->name ?? 'Unknown';

                        $failed = [];
                        $queuedCount = 0;
                        foreach ($admins as $admin) {
                            // Validate email format strictly before queueing
                            $email = trim((string) ($admin->email ?? ''));
                            $name = trim((string) ($admin->name ?? ''));
                            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                $failed[] = $email !== '' ? $email : 'invalid-email';
                                logger()->warning('Skipping admin notification due to invalid email', [
                                    'ticket_id' => $ticket->id,
                                    'recipient_id' => $admin->id ?? null,
                                    'raw_email' => $admin->email ?? null,
                                ]);
                                continue;
                            }

                            // Queue individually to avoid exposing recipients to each other
                            try {
                                // Bind campus_smtp mailer to the mailable so queued jobs use the correct transport
                                Mail::to(new Address($email, $name))
                                    ->queue(
                                        (new TicketCreated(
                                            ticketId: $ticket->id,
                                            title: $ticket->title,
                                            priority: $ticket->priority,
                                            status: $ticket->status,
                                            submitterName: $submitterName,
                                            ticketUrl: $ticketUrl,
                                        ))->mailer('campus_smtp')
                                    );
                                $queuedCount++;
                            } catch (\Throwable $t) {
                                $failed[] = $admin->email ?? 'unknown';
                                // Detailed per-recipient logging to surface SMTP/transport errors
                                $context = [
                                    'ticket_id' => $ticket->id,
                                    'recipient_id' => $admin->id ?? null,
                                    'recipient_email' => $admin->email ?? null,
                                    'recipient_name' => $admin->name ?? null,
                                    'exception' => get_class($t),
                                    'message' => $t->getMessage(),
                                ];
                                if (config('app.debug')) {
                                    $context['trace'] = $t->getTraceAsString();
                                }
                                logger()->error('Failed dispatching ticket admin notification to queue', $context);
                            }
                        }

                        if ($queuedCount === 0) {
                            $mailWarning = 'Ticket created, but no valid admin email addresses were available to notify.'
                                . (!empty($failed) ? ' Invalid: ' . implode(', ', $failed) : '');
                        } elseif (!empty($failed)) {
                            $mailWarning = 'Ticket created, but failed to enqueue notification for some admin(s): ' . implode(', ', $failed);
                        }
                    }
                    else {
                        $mailWarning = 'Ticket created, but no ticket admins with email were found to notify.';
                    }
                }
                else {
                    $mailWarning = 'Ticket created, but no Tickets admin role was found for the current app context.';
                }
            }
        } catch (\Throwable $e) {
            // Do not interrupt ticket creation on mail failures; optionally log in debug environments
            if (config('app.debug')) {
                logger()->warning('Ticket admin notification enqueue failed', [
                    'ticket_id' => $ticket->id,
                    'error' => $e->getMessage(),
                ]);
            }
            $mailWarning = $mailWarning ?: 'Ticket created, but failed to enqueue admin notification due to a mail error.';
        }

        $redirect = redirect()
            ->route('tickets.show', $ticket)
            ->with('success', 'Ticket created successfully!');

        if ($mailWarning) {
            $redirect->with('warning', $mailWarning);
        }

        return $redirect;
    }

    /**
     * Display the specified resource.
     */
    public function show(Ticket $ticket)
    {
        $this->authorize('view', $ticket);

        $ticket->load([
            'user',
            'files',
            'assignees',
            'updatedBy',
            'comments' => function ($q) {
                $q->with(['user:id,name,email'])
                  ->orderBy('created_at', 'asc');
            },
        ]);
        
        // Get team member information if user has an email
        $userWithTeam = null;
        if ($ticket->user && $ticket->user->email) {
            $teamMember = Team::where('email', $ticket->user->email)->first();
            if ($teamMember) {
                $userWithTeam = [
                    'id' => $ticket->user->id,
                    'name' => $ticket->user->name,
                    'email' => $ticket->user->email,
                    'is_team_member' => true,
                    'team_id' => $teamMember->id
                ];
            }
        }
        
        // Enrich assignees with directory info when their email matches a team member
        $assigneesWithTeam = $ticket->assignees->map(function ($u) {
            $team = $u->email ? Team::where('email', $u->email)->first() : null;
            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'is_team_member' => (bool) $team,
                'team_id' => $team?->id,
            ];
        })->values();
        
        // Enrich updated_by_user with directory info when their email matches a team member
        $updatedByWithTeam = null;
        if ($ticket->updatedBy && $ticket->updatedBy->email) {
            $updatedTeam = Team::where('email', $ticket->updatedBy->email)->first();
            if ($updatedTeam) {
                $updatedByWithTeam = [
                    'id' => $ticket->updatedBy->id,
                    'name' => $ticket->updatedBy->name,
                    'email' => $ticket->updatedBy->email,
                    'is_team_member' => true,
                    'team_id' => $updatedTeam->id,
                ];
            }
        }
        
        return Inertia::render('Tickets/Show', [
            'ticket' => array_merge($ticket->toArray(), [
                'files' => $ticket->files,
                'assignees' => $assigneesWithTeam,
                'user' => $userWithTeam ?: ($ticket->user ? [
                    'id' => $ticket->user->id,
                    'name' => $ticket->user->name,
                    'email' => $ticket->user->email,
                    'is_team_member' => false
                ] : null),
                'updated_by_user' => $updatedByWithTeam ?: ($ticket->updatedBy ? [
                    'id' => $ticket->updatedBy->id,
                    'name' => $ticket->updatedBy->name,
                    'email' => $ticket->updatedBy->email,
                    'is_team_member' => false,
                ] : null),
            ]),
            'can' => [
                'update' => auth()->user()->can('update', $ticket),
                'delete' => auth()->user()->can('delete', $ticket),
                'changeStatus' => auth()->user()->can('changeStatus', $ticket),
                // Full control: approve/reject/complete (managers or users with update permission)
                'changeStatusAll' => auth()->user()->can('tickets.ticket.manage') || auth()->user()->can('tickets.ticket.update'),
            ],
            'isAssignee' => $ticket->assignees()->where('users.id', auth()->id())->exists(),
            'authUserId' => auth()->id(),
            'authUser' => auth()->user()->only(['id', 'name', 'email']),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Ticket $ticket)
    {
        $this->authorize('update', $ticket);
        
        // Load relationships needed for edit (files and assignees)
        $ticket->load(['files', 'assignees']);

        return Inertia::render('Tickets/Edit', [
            'ticket' => $ticket,
            'priorities' => ['Low', 'Medium', 'High'],
            'statuses' => ['Received', 'Approved', 'Rejected', 'Completed'],
            'users' => User::all()->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                ];
            }),
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
            'due_date' => 'nullable|date',
            'assigned_user_ids' => 'sometimes|array',
            'assigned_user_ids.*' => 'integer|exists:users,id',
        ]);

        // Fill allowed fields and update modifier
        $ticket->fill($validated);
        $ticket->updated_by = auth()->id();
        $ticket->save();

        // Sync multi assignees (pivot)
        $assignedIds = collect($request->input('assigned_user_ids', []))
            ->filter()
            ->map(fn($v) => (int) $v)
            ->unique()
            ->values();

        // If the field is present, sync (including empty to detach all)
        if ($request->has('assigned_user_ids')) {
            $ticket->assignees()->sync($assignedIds->all());
        }

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('success', 'Ticket updated successfully!');
    }

    /**
     * Update the status of the specified resource.
     */
    public function updateStatus(Request $request, Ticket $ticket)
    {
        $this->authorize('changeStatus', $ticket);

        $canChangeAll = auth()->user()->can('tickets.ticket.manage') || auth()->user()->can('tickets.ticket.update');

        // Only managers/updaters can approve/reject; assignees can only complete
        $allowed = $canChangeAll ? 'Approved,Rejected,Completed' : 'Completed';

        $validated = $request->validate([
            'status' => 'required|in:' . $allowed,
        ]);

        $ticket->status = $validated['status'];
        $ticket->updated_by = auth()->id();
        $ticket->save();

        // If approved, notify all assignees via email (non-blocking if mail fails)
        if ($validated['status'] === 'Approved') {
            $mailWarning = null;
            try {
                $assignees = $ticket->assignees()->whereNotNull('email')->get();
                if ($assignees->isNotEmpty()) {
                    $ticketUrl = route('tickets.show', $ticket);
                    $submitterName = optional($ticket->user)->name ?? 'Unknown';

                    $failed = [];
                    $queuedCount = 0;
                    foreach ($assignees as $assignee) {
                        $email = trim((string) ($assignee->email ?? ''));
                        $name = trim((string) ($assignee->name ?? ''));
                        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            $failed[] = $email !== '' ? $email : 'invalid-email';
                            logger()->warning('Skipping assignee approval notification due to invalid email', [
                                'ticket_id' => $ticket->id,
                                'recipient_id' => $assignee->id ?? null,
                                'raw_email' => $assignee->email ?? null,
                            ]);
                            continue;
                        }

                        try {
                            Mail::to(new Address($email, $name))
                                ->queue(
                                    (new TicketApproved(
                                        ticketId: $ticket->id,
                                        title: $ticket->title,
                                        priority: $ticket->priority,
                                        status: $ticket->status,
                                        submitterName: $submitterName,
                                        ticketUrl: $ticketUrl,
                                    ))->mailer('campus_smtp')
                                );
                            $queuedCount++;
                        } catch (\Throwable $t) {
                            $failed[] = $assignee->email ?? 'unknown';
                            $context = [
                                'ticket_id' => $ticket->id,
                                'recipient_id' => $assignee->id ?? null,
                                'recipient_email' => $assignee->email ?? null,
                                'recipient_name' => $assignee->name ?? null,
                                'exception' => get_class($t),
                                'message' => $t->getMessage(),
                            ];
                            if (config('app.debug')) {
                                $context['trace'] = $t->getTraceAsString();
                            }
                            logger()->error('Failed dispatching ticket approval notification to queue', $context);
                        }
                    }

                    if ($queuedCount === 0) {
                        $mailWarning = 'Ticket approved, but no valid assignee email addresses were available to notify.'
                            . (!empty($failed) ? ' Invalid: ' . implode(', ', $failed) : '');
                    } elseif (!empty($failed)) {
                        $mailWarning = 'Ticket approved, but failed to enqueue notification for some assignee(s): ' . implode(', ', $failed);
                    }
                } else {
                    $mailWarning = 'Ticket approved, but there are no assignees with email to notify.';
                }
            } catch (\Throwable $e) {
                if (config('app.debug')) {
                    logger()->warning('Ticket assignee approval notification enqueue failed', [
                        'ticket_id' => $ticket->id,
                        'error' => $e->getMessage(),
                    ]);
                }
                $mailWarning = $mailWarning ?: 'Ticket approved, but failed to enqueue assignee notifications due to a mail error.';
            }

            if ($mailWarning) {
                return redirect()
                    ->back()
                    ->with('success', 'Ticket status updated successfully!')
                    ->with('warning', $mailWarning);
            }
        }

        // If completed, notify the submitter (ticket owner)
        if ($validated['status'] === 'Completed') {
            $mailWarning = null;
            try {
                $submitter = $ticket->user; // may be null
                $email = trim((string) ($submitter->email ?? ''));
                $name = trim((string) ($submitter->name ?? ''));

                if ($submitter && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $ticketUrl = route('tickets.show', $ticket);
                    $resolverName = auth()->user()->name ?? 'Resolver';

                    try {
                        Mail::to(new Address($email, $name))
                            ->queue(
                                (new TicketCompleted(
                                    ticketId: $ticket->id,
                                    title: $ticket->title,
                                    priority: $ticket->priority,
                                    status: $ticket->status,
                                    resolverName: $resolverName,
                                    ticketUrl: $ticketUrl,
                                ))->mailer('campus_smtp')
                            );
                    } catch (\Throwable $t) {
                        $context = [
                            'ticket_id' => $ticket->id,
                            'recipient_id' => $submitter->id ?? null,
                            'recipient_email' => $submitter->email ?? null,
                            'recipient_name' => $submitter->name ?? null,
                            'exception' => get_class($t),
                            'message' => $t->getMessage(),
                        ];
                        if (config('app.debug')) {
                            $context['trace'] = $t->getTraceAsString();
                        }
                        logger()->error('Failed dispatching ticket completion notification to queue', $context);
                        $mailWarning = 'Ticket completed, but failed to enqueue notification to the submitter.';
                    }
                } else {
                    $mailWarning = 'Ticket completed, but the submitter has no valid email to notify.';
                }
            } catch (\Throwable $e) {
                if (config('app.debug')) {
                    logger()->warning('Ticket submitter completion notification enqueue failed', [
                        'ticket_id' => $ticket->id,
                        'error' => $e->getMessage(),
                    ]);
                }
                $mailWarning = $mailWarning ?: 'Ticket completed, but failed to enqueue submitter notification due to a mail error.';
            }

            if ($mailWarning) {
                return redirect()
                    ->back()
                    ->with('success', 'Ticket status updated successfully!')
                    ->with('warning', $mailWarning);
            }
        }

        // If rejected, notify the submitter (ticket owner)
        if ($validated['status'] === 'Rejected') {
            $mailWarning = null;
            try {
                $submitter = $ticket->user; // may be null
                $email = trim((string) ($submitter->email ?? ''));
                $name = trim((string) ($submitter->name ?? ''));

                if ($submitter && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $ticketUrl = route('tickets.show', $ticket);
                    $reviewerName = auth()->user()->name ?? 'Reviewer';

                    try {
                        Mail::to(new Address($email, $name))
                            ->queue(
                                (new TicketRejected(
                                    ticketId: $ticket->id,
                                    title: $ticket->title,
                                    priority: $ticket->priority,
                                    status: $ticket->status,
                                    reviewerName: $reviewerName,
                                    ticketUrl: $ticketUrl,
                                ))->mailer('campus_smtp')
                            );
                    } catch (\Throwable $t) {
                        $context = [
                            'ticket_id' => $ticket->id,
                            'recipient_id' => $submitter->id ?? null,
                            'recipient_email' => $submitter->email ?? null,
                            'recipient_name' => $submitter->name ?? null,
                            'exception' => get_class($t),
                            'message' => $t->getMessage(),
                        ];
                        if (config('app.debug')) {
                            $context['trace'] = $t->getTraceAsString();
                        }
                        logger()->error('Failed dispatching ticket rejection notification to queue', $context);
                        $mailWarning = 'Ticket rejected, but failed to enqueue notification to the submitter.';
                    }
                } else {
                    $mailWarning = 'Ticket rejected, but the submitter has no valid email to notify.';
                }
            } catch (\Throwable $e) {
                if (config('app.debug')) {
                    logger()->warning('Ticket submitter rejection notification enqueue failed', [
                        'ticket_id' => $ticket->id,
                        'error' => $e->getMessage(),
                    ]);
                }
                $mailWarning = $mailWarning ?: 'Ticket rejected, but failed to enqueue submitter notification due to a mail error.';
            }

            if ($mailWarning) {
                return redirect()
                    ->back()
                    ->with('success', 'Ticket status updated successfully!')
                    ->with('warning', $mailWarning);
            }
        }

        return redirect()
            ->back()
            ->with('success', 'Ticket status updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Ticket $ticket)
    {
        $this->authorize('delete', $ticket);

        // Delete all ticket files from storage
        foreach ($ticket->files as $file) {
            Storage::disk('public')->delete($file->file_path);
        }

        // Delete all comment attachments from storage
        foreach ($ticket->comments as $comment) {
            foreach ($comment->attachments as $attachment) {
                Storage::disk('public')->delete($attachment->file_path);
            }
        }

        // Delete the entire ticket folder if it exists (includes both ticket files and comment attachments)
        $ticketFolder = "tickets/{$ticket->id}";
        if (Storage::disk('public')->exists($ticketFolder)) {
            Storage::disk('public')->deleteDirectory($ticketFolder);
        }

        // Clean up old comment attachments folder structure (for backward compatibility)
        $commentAttachmentsFolder = "comment-attachments/ticket-{$ticket->id}";
        if (Storage::disk('public')->exists($commentAttachmentsFolder)) {
            Storage::disk('public')->deleteDirectory($commentAttachmentsFolder);
        }

        $ticket->delete();

        return redirect()
            ->route('tickets.index')
            ->with('success', 'Ticket deleted successfully!');
    }
}
