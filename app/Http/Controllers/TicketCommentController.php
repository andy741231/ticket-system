<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketComment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
            'body' => 'required|string|max:5000',
        ]);

        TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => Auth::id(),
            'body' => $validated['body'],
        ]);

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('status', 'Comment added successfully!');
    }

    /**
     * Remove the specified comment from storage.
     */
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

        $comment->delete();

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('status', 'Comment deleted successfully!');
    }
}
