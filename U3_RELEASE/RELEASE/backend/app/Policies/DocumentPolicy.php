<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    /**
     * Admins bypass all checks via Gate::before in AppServiceProvider.
     */

    public function viewAny(User $user): bool
    {
        return true; // all authenticated users can list their own documents
    }

    public function view(User $user, Document $document): bool
    {
        if ($document->visibility === Document::VISIBILITY_PUBLIC) {
            return true;
        }
        if ($document->visibility === Document::VISIBILITY_SHARED) {
            return true; // any authenticated user
        }
        return $document->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->is_active;
    }

    public function update(User $user, Document $document): bool
    {
        return $document->user_id === $user->id;
    }

    public function delete(User $user, Document $document): bool
    {
        return $document->user_id === $user->id;
    }

    public function reprocess(User $user, Document $document): bool
    {
        return $document->user_id === $user->id
            && in_array($document->status, [Document::STATUS_FAILED, Document::STATUS_COMPLETED]);
    }

    public function viewChunks(User $user, Document $document): bool
    {
        return $this->view($user, $document);
    }
}
