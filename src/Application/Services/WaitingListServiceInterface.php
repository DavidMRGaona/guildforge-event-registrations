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
     * Promote the next user from waiting list to confirmed.
     * Called when a participant cancels and a spot becomes available.
     */
    public function promoteNext(string $eventId): ?EventRegistrationResponseDTO;

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
