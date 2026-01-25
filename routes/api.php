<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\EventRegistrations\Http\Controllers\EventRegistrationController;

// Use 'web' middleware for session-based authentication (Inertia SPA)
Route::middleware('web')
    ->prefix('api/events/{eventId}/registration')
    ->whereUuid('eventId')
    ->name('event-registrations.api.')
    ->group(function (): void {
        // Public endpoint - get event registration status
        Route::get('/status', [EventRegistrationController::class, 'status'])
            ->name('status');

        // Authenticated endpoints
        Route::middleware('auth')->group(function (): void {
            // Get current user's registration for this event
            Route::get('/', [EventRegistrationController::class, 'show'])
                ->name('show');

            // Register to event
            Route::post('/', [EventRegistrationController::class, 'store'])
                ->name('store');

            // Cancel registration
            Route::delete('/', [EventRegistrationController::class, 'destroy'])
                ->name('destroy');
        });
    });

// User's registrations
Route::middleware(['web', 'auth'])
    ->prefix('api')
    ->name('event-registrations.api.')
    ->group(function (): void {
        Route::get('/my-registrations', [EventRegistrationController::class, 'myRegistrations'])
            ->name('my-registrations');
    });
