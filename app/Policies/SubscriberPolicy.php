<?php

namespace App\Policies;

use App\Models\Newsletter\Subscriber;
use App\Models\User;

class SubscriberPolicy
{
    /**
     * Determine whether the user can view any models.
     *
     * Newsletter admins (newsletter.manage) can access the subscribers listing.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('newsletter.manage');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Subscriber $subscriber): bool
    {
        return $user->can('newsletter.manage');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('newsletter.manage');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Subscriber $subscriber): bool
    {
        return $user->can('newsletter.manage');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Subscriber $subscriber): bool
    {
        return $user->can('newsletter.manage');
    }
}
