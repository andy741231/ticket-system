<?php

namespace App\Policies;

use App\Models\Invite;
use App\Models\User;

class InvitePolicy
{
    /**
     * Determine whether the user can view any invites.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'super_admin']) || $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can view the invite.
     */
    public function view(User $user, Invite $invite): bool
    {
        return $user->hasRole(['admin', 'super_admin']) || 
               $user->isSuperAdmin() || 
               $user->id === $invite->invited_by_user_id;
    }

    /**
     * Determine whether the user can create invites.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'super_admin']) || $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can update the invite.
     */
    public function update(User $user, Invite $invite): bool
    {
        return $user->hasRole(['admin', 'super_admin']) || 
               $user->isSuperAdmin() || 
               $user->id === $invite->invited_by_user_id;
    }

    /**
     * Determine whether the user can delete the invite.
     */
    public function delete(User $user, Invite $invite): bool
    {
        return $user->hasRole(['admin', 'super_admin']) || 
               $user->isSuperAdmin() || 
               $user->id === $invite->invited_by_user_id;
    }
}
