<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     *
     * Only admins can view the list of users.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('hub.user.view') || $user->can('hub.user.manage');
    }

    /**
     * Determine whether the user can view the model.
     *
     * Admins can view any user, users can only view their own profile.
     */
    public function view(User $user, User $model): bool
    {
        return $user->can('hub.user.manage') || $user->can('hub.user.view') || $user->id === $model->id;
    }

    /**
     * Determine whether the user can create models.
     *
     * Only admins can create new users.
     */
    public function create(User $user): bool
    {
        return $user->can('hub.user.manage') || $user->can('hub.user.create');
    }

    /**
     * Determine whether the user can update the model.
     *
     * Admins can update any user, users can only update their own profile.
     */
    public function update(User $user, User $model): bool
    {
        return $user->can('hub.user.manage') || $user->can('hub.user.update') || $user->id === $model->id;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * Only admins can delete users, and they can't delete themselves.
     */
    public function delete(User $user, User $model): bool
    {
        return ($user->can('hub.user.manage') || $user->can('hub.user.delete')) && $user->id !== $model->id;
    }

    /**
     * Determine whether the user can change the role of another user.
     *
     * Only admins can change user roles.
     */
    public function changeRole(User $user, User $model): bool
    {
        return $user->can('hub.user.manage') && $user->id !== $model->id;
    }

    /**
     * Determine whether the user can view the user's tickets.
     *
     * Admins can view any user's tickets, users can only view their own tickets.
     */
    public function viewTickets(User $user, User $model): bool
    {
        return $user->can('hub.user.manage') || $user->id === $model->id;
    }
}