<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\EventRegistrations\Http\Controllers\EventRegistrationController;

// API-like routes using web middleware for session-based auth (Inertia SPA)
// Note: These are in web.php (not api.php) because ModuleServiceProvider adds
// prefix('api') to api.php files, which would result in /api/api/...
Route::prefix('api/events/{eventId}/registration')
    ->whereUuid('eventId')
    ->name('event-registrations.api.')
    ->group(function (): void {
        // Public endpoint - get event registration status
        Route::get('/status', [EventRegistrationController::class, 'status'])
            ->name('status');

        // Authenticated endpoints
        Route::middleware('auth')->group(function (): void {
            Route::get('/', [EventRegistrationController::class, 'show'])
                ->name('show');
            Route::post('/', [EventRegistrationController::class, 'store'])
                ->name('store');
            Route::delete('/', [EventRegistrationController::class, 'destroy'])
                ->name('destroy');
        });
    });

// User's registrations
Route::middleware('auth')
    ->prefix('api')
    ->name('event-registrations.api.')
    ->group(function (): void {
        Route::get('/my-registrations', [EventRegistrationController::class, 'myRegistrations'])
            ->name('my-registrations');
    });
