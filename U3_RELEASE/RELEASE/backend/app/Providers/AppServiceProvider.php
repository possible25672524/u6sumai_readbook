<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Document;
use App\Models\User;
use App\Policies\CategoryPolicy;
use App\Policies\DocumentPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ── Admin bypass: admin role can do anything ──────────────
        Gate::before(function (User $user, string $ability) {
            if ($user->role?->slug === 'admin') {
                return true;
            }
        });

        // ── Phase 1: Permission-based gates ───────────────────────
        $permissions = [
            'manage-users',
            'manage-roles-permissions',
            'view-system-logs',
            'backup-database',
            'view-system-stats',
            'create-document',
            'manage-own-documents',
            'create-quiz',
            'view-own-analytics',
            'view-documents',
            'take-quiz',
            'view-own-progress',
        ];

        foreach ($permissions as $permission) {
            Gate::define($permission, function (User $user) use ($permission) {
                return $user->role?->permissions()
                    ->where('slug', $permission)
                    ->exists() ?? false;
            });
        }

        // ── Phase 2: Model policies ───────────────────────────────
        Gate::policy(Document::class, DocumentPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
    }
}
