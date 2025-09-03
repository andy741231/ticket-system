<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TicketPolicy
{
    /**
     * Determine whether the user can view any models.
     *
     * Admins can view all tickets, users can only view their own tickets.
     */
    public function viewAny(User $user): bool
    {
        // Users with explicit view or manage permission can access listing.
        // Additional filtering by ownership can still be applied in controllers.
        return $user->can('tickets.ticket.view') || $user->can('tickets.ticket.manage');
    }

    /**
     * Determine whether the user can view the model.
     *
     * Admins can view any ticket. Non-admins can only view their own tickets
     * and tickets where they are assigned. Possessing a generic "view" permission
     * does not grant access to arbitrary tickets by ID.
     */
    public function view(User $user, Ticket $ticket): bool
    {
        // Allow if user manages or can update tickets, owns the ticket, or is assigned
        return $user->can('tickets.ticket.manage')
            || $user->can('tickets.ticket.update')
            || $ticket->user_id === $user->id
            || $ticket->assignees()->where('users.id', $user->id)->exists();
    }

    /**
     * Determine whether the user can create models.
     *
     * Any authenticated user can create tickets.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     *
     * Admins can update any ticket.
     * Users can only update their own tickets if the status is 'Received'.
     */
    public function update(User $user, Ticket $ticket): bool
    {
        // Users with explicit permission can update any ticket
        if ($user->can('tickets.ticket.manage') || $user->can('tickets.ticket.update')) {
            return true;
        }

        // Owners can update when ticket is still in 'Received' status
        return $ticket->user_id === $user->id && $ticket->status === 'Received';
    }

    /**
     * Determine whether the user can delete the model.
     *
     * Only admins can delete tickets.
     */
    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->can('tickets.ticket.manage') || $user->can('tickets.ticket.delete');
    }

    /**
     * Determine whether the user can change the status of the ticket.
     *
     * Only admins can change the status of a ticket.
     */
    public function changeStatus(User $user, Ticket $ticket): bool
    {
        // Admins or users with explicit update permission can change any status
        if ($user->can('tickets.ticket.manage') || $user->can('tickets.ticket.update')) {
            return true;
        }

        // Also allow assigned users to change status (controller will restrict to 'Completed')
        return $ticket->assignees()->where('users.id', $user->id)->exists();
    }

    /**
     * Determine whether the user can view the ticket's author.
     *
     * Only admins can see who created a ticket.
     */
    public function viewAuthor(User $user, Ticket $ticket): bool
    {
        return $user->can('tickets.ticket.manage');
    }
}
