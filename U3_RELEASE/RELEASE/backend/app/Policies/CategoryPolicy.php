<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Category $category): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return in_array($user->role?->slug, ['admin', 'teacher']);
    }

    public function update(User $user, Category $category): bool
    {
        if ($user->role?->slug === 'admin') {
            return true;
        }
        return $user->role?->slug === 'teacher'
            && $category->created_by === $user->id;
    }

    public function delete(User $user, Category $category): bool
    {
        if ($user->role?->slug === 'admin') {
            return true;
        }
        return $user->role?->slug === 'teacher'
            && $category->created_by === $user->id
            && $category->documents()->count() === 0;
    }
}
