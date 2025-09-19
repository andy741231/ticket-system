<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketCommentReaction;
use App\Models\User;
use App\Mail\CommentMentionNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;

class TicketCommentController extends Controller
{
    /**
     * Extract mentions from comment text and return valid users
     */
    private function extractValidMentions(string $text, Ticket $ticket): array
    {
        // Extract @mentions: capture only the username token after '@' (stop at first whitespace)
        // This avoids mis-parsing cases like "@andy this ..." as a two-word full name.
        preg_match_all('/@([a-zA-Z0-9_\-]+)/', $text, $matches);
        $mentionedUsernames = array_unique($matches[1]);
        
        if (empty($mentionedUsernames)) {
            return [];
        }
        
        // Get users who have access to this ticket
        $validUsers = $this->getUsersWithTicketAccess($ticket);
        
        // Filter mentioned users to only those who have access
        $validMentions = [];
        foreach ($mentionedUsernames as $username) {
            $username = trim($username); // Clean up whitespace
            
            // First, try to find exact username match
            $user = $validUsers->first(function ($user) use ($username) {
                return !empty($user->username) && strcasecmp($user->username, $username) === 0;
            });
            
            // If no username match, try other fields
            if (!$user) {
                $user = $validUsers->first(function ($user) use ($username) {
                    // Check first name
                    if (!empty($user->first_name) && strcasecmp($user->first_name, $username) === 0) {
                        return true;
                    }
                    
                    // Check last name
                    if (!empty($user->last_name) && strcasecmp($user->last_name, $username) === 0) {
                        return true;
                    }
                    
                    // Check first name + last name combination
                    $fullName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
                    if (!empty($fullName) && strcasecmp($fullName, $username) === 0) {
                        return true;
                    }
                    
                    // Check full name (display name)
                    if (!empty($user->name) && strcasecmp($user->name, $username) === 0) {
                        return true;
                    }
                    
                    return false;
                });
            }
            
            // Matching attempt complete; proceed if a user was found
            
            if ($user) {
                $validMentions[] = $user->id;
            }
        }
        
        return array_unique($validMentions);
    }
    
    /**
     * Get all users who have access to the ticket
     */
    private function getUsersWithTicketAccess(Ticket $ticket)
    {
        // Get users who can view this ticket:
        // 1. Ticket owner
        // 2. Assigned users
        // 3. Users with manage/update permissions
        // 4. Global super admins (role: super_admin)
        
        $userIds = collect([$ticket->user_id]);
        
        // Add assigned users
        $assignedUserIds = $ticket->assignees()->pluck('users.id');
        $userIds = $userIds->merge($assignedUserIds);
        
        // Add users with manage/update permissions
        $managementUsers = User::whereHas('roles.permissions', function ($query) {
            $query->whereIn('name', ['tickets.ticket.manage', 'tickets.ticket.update']);
        })->pluck('id');
        $userIds = $userIds->merge($managementUsers);

        // Add super admins explicitly (regardless of per-app grants)
        try {
            $superRoleIds = \Spatie\Permission\Models\Role::query()
                ->where('name', 'super_admin')
                ->pluck('id');
            if ($superRoleIds->isNotEmpty()) {
                $superAdminIds = \DB::table('model_has_roles')
                    ->whereIn('role_id', $superRoleIds)
                    ->where('model_type', User::class)
                    ->pluck('model_id');
                $userIds = $userIds->merge($superAdminIds);
            }
        } catch (\Throwable $e) {
            // Fail open: if role table missing in some env, just skip adding super admins
        }
        
        $users = User::whereIn('id', $userIds->unique())->get();
        
        // Users with access computed
        
        return $users;
    }

    /**
     * Store a newly created comment for the given ticket.
     */
    public function store(Request $request, Ticket $ticket)
    {
        // Ensure the user can view the ticket (viewers, owners, or assignees)
        $this->authorize('view', $ticket);

        $validated = $request->validate([
            // Allow empty body when files are attached; require one of body or files
            'body' => 'nullable|string|max:5000|required_without:files',
            'parent_id' => 'nullable|exists:ticket_comments,id',
            'files' => 'nullable|array|required_without:body',
            'files.*' => 'file|max:10240', // 10MB max per file
        ]);

        // Extract valid mentions from comment body
        $mentions = [];
        if (!empty($validated['body'])) {
            $mentions = $this->extractValidMentions($validated['body'], $ticket);
        }

        $comment = TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => Auth::id(),
            // Save empty string if body is omitted to satisfy non-null DB columns
            'body' => $validated['body'] ?? '',
            'parent_id' => $validated['parent_id'] ?? null,
            'mentions' => $mentions,
        ]);

        // Handle file attachments
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store("tickets/{$ticket->id}/comment", 'public');
                $comment->attachments()->create([
                    'original_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            }
        }

        // Send mention notifications
        if (!empty($mentions)) {
            $currentUser = Auth::user();
            $mentionedUsers = User::whereIn('id', $mentions)->get();
            
            foreach ($mentionedUsers as $mentionedUser) {
                // Don't send notification to the user who made the comment
                if ($mentionedUser->id !== $currentUser->id) {
                    Mail::to($mentionedUser->email)
                        ->send(new CommentMentionNotification($ticket, $comment, $mentionedUser, $currentUser));
                } else {
                    // Skip notifying the author on self-mention
                }
            }
        }

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('success', 'Comment added successfully!');
    }

    /**
     * Remove the specified comment from storage.
     */
    public function update(Request $request, Ticket $ticket, TicketComment $comment)
    {
        // Ensure comment belongs to the ticket
        if ($comment->ticket_id !== $ticket->id) {
            abort(404);
        }

        $user = Auth::user();

        // Only allow editing own comments
        if ($comment->user_id !== $user->id) {
            abort(403);
        }

        $validated = $request->validate([
            'body' => 'required|string|max:5000',
            'files' => 'nullable|array',
            'files.*' => 'file|max:10240', // 10MB max per file
        ]);

        // Extract valid mentions from updated comment body
        $mentions = $this->extractValidMentions($validated['body'], $ticket);

        $comment->update([
            'body' => $validated['body'],
            'mentions' => $mentions,
        ]);

        // Handle new file attachments
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store("tickets/{$ticket->id}/comment", 'public');
                $comment->attachments()->create([
                    'original_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            }
        }

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('success', 'Comment updated successfully!');
    }

    public function destroy(Ticket $ticket, TicketComment $comment)
    {
        // Ensure comment belongs to the ticket
        if ($comment->ticket_id !== $ticket->id) {
            abort(404);
        }

        $user = Auth::user();

        // Allow deletion if user owns the comment or has ticket manage permission
        $canManage = $user->can('tickets.ticket.manage') || $user->can('tickets.ticket.delete');
        if ($comment->user_id !== $user->id && !$canManage) {
            abort(403);
        }

        // Delete associated attachments from storage
        foreach ($comment->attachments as $attachment) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        $comment->delete();

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('success', 'Comment deleted successfully!');
    }

    public function reactions(Request $request, Ticket $ticket, TicketComment $comment)
    {
        // Ensure comment belongs to the ticket
        if ($comment->ticket_id !== $ticket->id) {
            abort(404);
        }

        $this->authorize('view', $ticket);

        $validated = $request->validate([
            'reaction' => 'required|string|max:10',
            'action' => 'required|in:add,remove',
        ]);

        $userId = Auth::id();
        $reaction = $validated['reaction'];
        $action = $validated['action'];

        if ($action === 'add') {
            TicketCommentReaction::firstOrCreate([
                'comment_id' => $comment->id,
                'user_id' => $userId,
                'type' => $reaction,
            ]);
        } else {
            TicketCommentReaction::where([
                'comment_id' => $comment->id,
                'user_id' => $userId,
                'type' => $reaction,
            ])->delete();
        }

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('info', 'Reaction updated!');
    }

    /**
     * Toggle pinned state of a comment (admins/managers only).
     */
    public function pin(Request $request, Ticket $ticket, TicketComment $comment)
    {
        // Ensure comment belongs to the ticket
        if ($comment->ticket_id !== $ticket->id) {
            abort(404);
        }

        // Require ticket manage permission to pin/unpin
        $user = Auth::user();
        if (!$user || !$user->can('tickets.ticket.manage')) {
            abort(403);
        }

        $comment->update([
            'pinned' => !$comment->pinned,
        ]);

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('info', $comment->pinned ? 'Comment pinned!' : 'Comment unpinned!');
    }

    /**
     * Get users available for mentioning in this ticket
     */
    public function mentionableUsers(Ticket $ticket)
    {
        $this->authorize('view', $ticket);
        
        $users = $this->getUsersWithTicketAccess($ticket);
        
        // Exclude the current user from mentionable users
        $currentUserId = Auth::id();
        $users = $users->filter(function ($user) use ($currentUserId) {
            return $user->id !== $currentUserId;
        });
        
        $mappedUsers = $users->values()->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username ?? $user->name,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'searchable_names' => [
                    $user->username ?? $user->name,
                    $user->name,
                    $user->first_name,
                    $user->last_name,
                    trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))
                ]
            ];
        });
        
        return response()->json([
            'users' => $mappedUsers->toArray()
        ]);
    }
}
