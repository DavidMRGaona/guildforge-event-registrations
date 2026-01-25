<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Domain\Repositories;

use Modules\EventRegistrations\Domain\Entities\EventRegistration;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Domain\ValueObjects\EventRegistrationId;

interface EventRegistrationRepositoryInterface
{
    /**
     * Save a registration (create or update).
     */
    public function save(EventRegistration $registration): void;

    /**
     * Find a registration by ID.
     */
    public function find(EventRegistrationId $id): ?EventRegistration;

    /**
     * Find a registration by ID or throw an exception.
     */
    public function findOrFail(EventRegistrationId $id): EventRegistration;

    /**
     * Find a registration by user and event.
     */
    public function findByUserAndEvent(string $userId, string $eventId): ?EventRegistration;

    /**
     * Delete a registration.
     */
    public function delete(EventRegistrationId $id): void;

    /**
     * Get all registrations for an event.
     *
     * @return array<EventRegistration>
     */
    public function findByEvent(string $eventId): array;

    /**
     * Get registrations for an event with a specific state.
     *
     * @return array<EventRegistration>
     */
    public function findByEventAndState(string $eventId, RegistrationState $state): array;

    /**
     * Get all registrations for a user.
     *
     * @return array<EventRegistration>
     */
    public function findByUser(string $userId): array;

    /**
     * Count confirmed registrations for an event.
     */
    public function countConfirmedByEvent(string $eventId): int;

    /**
     * Count waiting list registrations for an event.
     */
    public function countWaitingListByEvent(string $eventId): int;

    /**
     * Get the next available position in the waiting list.
     */
    public function getNextWaitingListPosition(string $eventId): int;

    /**
     * Get the first registration in the waiting list (lowest position).
     */
    public function findFirstInWaitingList(string $eventId): ?EventRegistration;

    /**
     * Get all registrations in the waiting list ordered by position.
     *
     * @return array<EventRegistration>
     */
    public function findWaitingListOrdered(string $eventId): array;
}
