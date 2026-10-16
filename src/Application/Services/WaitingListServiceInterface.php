<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Application\Services;

use Modules\EventRegistrations\Application\DTOs\EventRegistrationResponseDTO;
use Modules\EventRegistrations\Domain\Entities\EventRegistration;

interface WaitingListServiceInterface
{
    /**
     * Add a user to the waiting list.
     *
     * @param  array<string, mixed>  $formData
     * @param  EventRegistration|null  $existingRegistration  Optional existing cancelled registration to reactivate
     */
    public function addToWaitingList(
        string $eventId,
        string $userId,
        array $formData = [],
        ?EventRegistration $existingRegistration = null,
    ): EventRegistrationResponseDTO;

    /**
     * Promote the next user from waiting list to confirmed, skipping $excludeRegistrationId.
     * Promotes one person with no capacity check: callers that react to a freed spot
     * use fillFreeSpots(), the capacity-aware entry point, which checks the free spots first.
     * Promotes nobody (returns null) once the event has started.
     */
    public function promoteNext(string $eventId, ?string $excludeRegistrationId = null): ?EventRegistrationResponseDTO;

    /**
     * Promote from the waiting list until the free confirmed spots are filled, before the event
     * starts only. Called whenever a confirmed spot may have been freed, by a user or by the staff.
     * $excludeRegistrationId is never promoted (a registration the staff has just moved there).
     * It always renumbers the waiting list afterwards, even when nobody was promoted.
     *
     * @return int How many registrations were promoted
     */
    public function fillFreeSpots(string $eventId, ?string $excludeRegistrationId = null): int;

    /**
     * Get a user's position in the waiting list.
     */
    public function getPosition(string $eventId, string $userId): ?int;

    /**
     * Get all users in the waiting list ordered by position.
     *
     * @return array<EventRegistrationResponseDTO>
     */
    public function getWaitingList(string $eventId): array;

    /**
     * Recalculate positions in the waiting list.
     * Used after a user is promoted or removed.
     */
    public function recalculatePositions(string $eventId): void;
}
