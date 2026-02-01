<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\EventRegistrations\Http\Controllers\EventRegistrationController;

/*
|--------------------------------------------------------------------------
| Event Registrations Web Routes
|--------------------------------------------------------------------------
|
| Routes for the Event Registrations module using web middleware for
| session-based functionality with Inertia.js frontend.
|
*/

Route::prefix('eventos/{eventId}/inscripcion')
    ->whereUuid('eventId')
    ->name('event-registrations.')
    ->group(function (): void {
        // Public endpoint - get event registration status
        Route::get('/estado', [EventRegistrationController::class, 'status'])
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
    ->name('event-registrations.')
    ->group(function (): void {
        Route::get('/mis-inscripciones', [EventRegistrationController::class, 'myRegistrations'])
            ->name('my-registrations');
    });
