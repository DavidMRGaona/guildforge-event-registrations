<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Application\Services;

use Modules\EventRegistrations\Application\DTOs\EventRegistrationConfigDTO;
use Modules\EventRegistrations\Application\DTOs\EventRegistrationResponseDTO;
use Modules\EventRegistrations\Application\DTOs\RegistrationListItemDTO;

interface RegistrationQueryServiceInterface
{
    /**
     * Get the registration status for an event (for public display).
     */
    public function getEventStatus(string $eventId): EventRegistrationConfigDTO;

    /**
     * Get all registrations for an event.
     *
     * @return array<RegistrationListItemDTO>
     */
    public function getEventRegistrations(string $eventId): array;

    /**
     * Get confirmed registrations for an event.
     *
     * @return array<RegistrationListItemDTO>
     */
    public function getConfirmedRegistrations(string $eventId): array;

    /**
     * Get waiting list for an event.
     *
     * @return array<RegistrationListItemDTO>
     */
    public function getWaitingList(string $eventId): array;

    /**
     * Get all registrations for a user.
     *
     * @return array<EventRegistrationResponseDTO>
     */
    public function getUserRegistrations(string $userId): array;

    /**
     * Get upcoming registrations for a user (events that haven't ended).
     *
     * @return array<EventRegistrationResponseDTO>
     */
    public function getUserUpcomingRegistrations(string $userId): array;

    /**
     * Count confirmed registrations for an event.
     */
    public function countConfirmed(string $eventId): int;

    /**
     * Count waiting list registrations for an event.
     */
    public function countWaitingList(string $eventId): int;
}
