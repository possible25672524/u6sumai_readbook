<?php
// INTEGRATION PATCH-01
// Fix: DEFECT-01 — AIServiceProvider missing from bootstrap/app.php
// File: backend/bootstrap/app.php
// Team: U5 Integration

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        apiPrefix: 'api',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Alias used in routes: middleware('role:admin')
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
        ]);
    })
    ->withProviders([
        // Phase 1: Gate definitions + Policy registration
        \App\Providers\AppServiceProvider::class,
        // Phase 2: Event -> Listener bindings
        \App\Providers\EventServiceProvider::class,
        // Phase 2 AI: AI Provider Layer (U2) — ADDED BY U5 INTEGRATION
        \App\Providers\AIServiceProvider::class,
    ])
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();
