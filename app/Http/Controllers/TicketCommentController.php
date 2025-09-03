<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketCommentReaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class TicketCommentController extends Controller
{
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

        $comment = TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => Auth::id(),
            // Save empty string if body is omitted to satisfy non-null DB columns
            'body' => $validated['body'] ?? '',
            'parent_id' => $validated['parent_id'] ?? null,
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

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('status', 'Comment added successfully!');
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

        $comment->update([
            'body' => $validated['body'],
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
            ->with('status', 'Comment updated successfully!');
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
            ->with('status', 'Comment deleted successfully!');
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
            ->with('status', 'Reaction updated!');
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
            ->with('status', $comment->pinned ? 'Comment pinned!' : 'Comment unpinned!');
    }
}
