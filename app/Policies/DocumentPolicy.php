<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    /**
     * Determine whether the user can view any documents.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('docs.document.view') || $user->can('docs.document.manage');
    }

    /**
     * Determine whether the user can view a specific document.
     * Managers can view any; owners can view their own.
     */
    public function view(User $user, Document $document): bool
    {
        if ($user->can('docs.document.manage') || $user->can('docs.document.update')) {
            return true;
        }

        return $document->user_id === $user->id;
    }

    /**
     * Determine whether the user can upload/create documents.
     */
    public function create(User $user): bool
    {
        return $user->can('docs.document.create') || $user->can('docs.document.manage');
    }

    /**
     * Determine whether the user can update a document.
     */
    public function update(User $user, Document $document): bool
    {
        if ($user->can('docs.document.manage') || $user->can('docs.document.update')) {
            return true;
        }

        return $document->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete a document.
     */
    public function delete(User $user, Document $document): bool
    {
        if ($user->can('docs.document.manage') || $user->can('docs.document.delete')) {
            return true;
        }

        return $document->user_id === $user->id;
    }
}
