<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\EventRegistrations\Application\DTOs\RegisterToEventDTO;
use Modules\EventRegistrations\Application\Services\EventRegistrationServiceInterface;
use Modules\EventRegistrations\Application\Services\RegistrationQueryServiceInterface;
use Modules\EventRegistrations\Domain\Exceptions\AlreadyRegisteredException;
use Modules\EventRegistrations\Domain\Exceptions\CannotCancelRegistrationException;
use Modules\EventRegistrations\Domain\Exceptions\EventFullException;
use Modules\EventRegistrations\Domain\Exceptions\RegistrationClosedException;
use Modules\EventRegistrations\Domain\Exceptions\RegistrationNotFoundException;
use Modules\EventRegistrations\Http\Requests\RegisterToEventRequest;

final class EventRegistrationController extends Controller
{
    public function __construct(
        private readonly EventRegistrationServiceInterface $registrationService,
        private readonly RegistrationQueryServiceInterface $queryService,
    ) {}

    /**
     * Get registration status for an event (public).
     */
    public function status(string $eventId): JsonResponse
    {
        $status = $this->queryService->getEventStatus($eventId);

        return response()->json([
            'data' => $status->toArray(),
        ]);
    }

    /**
     * Get current user's registration for an event.
     */
    public function show(Request $request, string $eventId): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json([
                'data' => null,
            ]);
        }

        $registration = $this->registrationService->getUserRegistration($eventId, (string) $user->id);

        return response()->json([
            'data' => $registration?->toArray(),
        ]);
    }

    /**
     * Register to an event.
     */
    public function store(RegisterToEventRequest $request, string $eventId): JsonResponse
    {
        $user = $request->user();

        try {
            $registration = $this->registrationService->register(new RegisterToEventDTO(
                eventId: $eventId,
                userId: (string) $user->id,
                formData: $request->validated('form_data', []),
                notes: $request->validated('notes'),
            ));

            return response()->json([
                'data' => $registration->toArray(),
                'message' => __('event-registrations::messages.success.registered'),
            ], 201);
        } catch (RegistrationClosedException $e) {
            return response()->json([
                'message' => __('event-registrations::messages.errors.registration_closed'),
                'error' => $e->getMessage(),
            ], 422);
        } catch (EventFullException $e) {
            return response()->json([
                'message' => __('event-registrations::messages.errors.event_full'),
                'error' => $e->getMessage(),
            ], 422);
        } catch (AlreadyRegisteredException $e) {
            return response()->json([
                'message' => __('event-registrations::messages.errors.already_registered'),
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Cancel registration to an event.
     */
    public function destroy(Request $request, string $eventId): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json([
                'message' => __('event-registrations::messages.errors.unauthenticated'),
            ], 401);
        }

        try {
            $this->registrationService->cancel($eventId, (string) $user->id);

            return response()->json([
                'message' => __('event-registrations::messages.success.cancelled'),
            ]);
        } catch (RegistrationNotFoundException $e) {
            return response()->json([
                'message' => __('event-registrations::messages.errors.not_found'),
                'error' => $e->getMessage(),
            ], 404);
        } catch (CannotCancelRegistrationException $e) {
            return response()->json([
                'message' => __('event-registrations::messages.errors.cannot_cancel'),
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get all registrations for the current user.
     */
    public function myRegistrations(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json([
                'message' => __('event-registrations::messages.errors.unauthenticated'),
            ], 401);
        }

        $registrations = $this->queryService->getUserRegistrations((string) $user->id);

        return response()->json([
            'data' => array_map(fn ($r) => $r->toArray(), $registrations),
        ]);
    }
}
