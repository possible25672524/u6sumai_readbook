<?php

use App\Http\Controllers\Api\Admin\PingController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\PasswordResetController;
use App\Http\Controllers\Api\Auth\ProfileController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\ProcessingJobController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — AI Study Assistant Platform
|--------------------------------------------------------------------------
|
| Phase 1: Auth + RBAC
| Phase 2: Document Upload + Processing Pipeline
|
*/

// ─── Public Auth Routes ───────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login',    [AuthController::class, 'login']);

    Route::post('forgot-password', [PasswordResetController::class, 'forgotPassword']);
    Route::post('reset-password',  [PasswordResetController::class, 'resetPassword']);
});

// ─── Authenticated Routes ─────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Auth management
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me',      [AuthController::class, 'me']);

        Route::put('profile',  [ProfileController::class, 'update']);
        Route::put('password', [ProfileController::class, 'changePassword']);
    });

    // ─── Categories ───────────────────────────────────────────────
    Route::apiResource('categories', CategoryController::class);

    // ─── Documents ────────────────────────────────────────────────
    Route::prefix('documents')->group(function () {
        Route::get('/',    [DocumentController::class, 'index']);
        Route::post('/',   [DocumentController::class, 'store']);

        Route::prefix('{document}')->group(function () {
            Route::get('/',            [DocumentController::class, 'show']);
            Route::put('/',            [DocumentController::class, 'update']);
            Route::delete('/',         [DocumentController::class, 'destroy']);

            // Processing
            Route::post('reprocess',   [DocumentController::class, 'reprocess']);
            Route::get('status',       [DocumentController::class, 'status']);

            // Content
            Route::get('chunks',       [DocumentController::class, 'chunks']);
            Route::get('transcript',   [DocumentController::class, 'transcript']);
            Route::get('download',     [DocumentController::class, 'download']);

            // Processing jobs for this document
            Route::get('jobs',         [ProcessingJobController::class, 'index']);
        });
    });

    // ─── Individual processing job detail ─────────────────────────
    Route::get('jobs/{job}', [ProcessingJobController::class, 'show']);

    // ─── Admin Routes ─────────────────────────────────────────────
    Route::prefix('admin')->middleware('role:admin')->group(function () {
        Route::get('ping', [PingController::class, 'ping']);
        Route::get('jobs', [ProcessingJobController::class, 'adminIndex']);
    });
});
