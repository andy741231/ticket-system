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
        return true; // The actual filtering is done in the controller
    }

    /**
     * Determine whether the user can view the model.
     *
     * Admins can view any ticket, users can only view their own tickets.
     */
    public function view(User $user, Ticket $ticket): bool
    {
        return $user->hasRole('admin') || $ticket->user_id === $user->id;
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
        if ($user->hasRole('admin')) {
            return true;
        }

        return $ticket->user_id === $user->id && $ticket->status === 'Received';
    }

    /**
     * Determine whether the user can delete the model.
     *
     * Only admins can delete tickets.
     */
    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can change the status of the ticket.
     *
     * Only admins can change the status of a ticket.
     */
    public function changeStatus(User $user, Ticket $ticket): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can view the ticket's author.
     *
     * Only admins can see who created a ticket.
     */
    public function viewAuthor(User $user, Ticket $ticket): bool
    {
        return $user->hasRole('admin');
    }
}
