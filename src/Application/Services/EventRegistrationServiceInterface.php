<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Application\Services;

use Modules\EventRegistrations\Application\DTOs\EventRegistrationResponseDTO;
use Modules\EventRegistrations\Application\DTOs\RegisterToEventDTO;
use Modules\EventRegistrations\Application\DTOs\UpdateRegistrationConfigDTO;
use Modules\EventRegistrations\Domain\Exceptions\AlreadyRegisteredException;
use Modules\EventRegistrations\Domain\Exceptions\CannotCancelRegistrationException;
use Modules\EventRegistrations\Domain\Exceptions\EventFullException;
use Modules\EventRegistrations\Domain\Exceptions\MembersOnlyException;
use Modules\EventRegistrations\Domain\Exceptions\RegistrationClosedException;
use Modules\EventRegistrations\Domain\Exceptions\RegistrationNotFoundException;

interface EventRegistrationServiceInterface
{
    /**
     * Register a user to an event.
     *
     * @throws RegistrationClosedException
     * @throws EventFullException
     * @throws AlreadyRegisteredException
     * @throws MembersOnlyException
     */
    public function register(RegisterToEventDTO $dto): EventRegistrationResponseDTO;

    /**
     * Whether the user meets the members-only requirement of the event: always true when the
     * event is open to everyone; for a members-only event, only association members do
     * (a guest, null, never does). The same check register() enforces.
     */
    public function meetsMembersOnly(string $eventId, ?string $userId): bool;

    /**
     * Cancel a user's registration.
     *
     * @throws RegistrationNotFoundException
     * @throws CannotCancelRegistrationException
     */
    public function cancel(string $eventId, string $userId): void;

    /**
     * Confirm a pending registration (admin action).
     *
     * @throws RegistrationNotFoundException
     */
    public function confirm(string $registrationId): EventRegistrationResponseDTO;

    /**
     * Reject a registration (admin action).
     *
     * @throws RegistrationNotFoundException
     */
    public function reject(string $registrationId): EventRegistrationResponseDTO;

    /**
     * Move a registration to the waiting list (admin action).
     *
     * @throws RegistrationNotFoundException
     */
    public function moveToWaitingList(string $registrationId): EventRegistrationResponseDTO;

    /**
     * Update registration config for an event.
     */
    public function updateConfig(UpdateRegistrationConfigDTO $dto): void;

    /**
     * Get a user's registration for an event.
     */
    public function getUserRegistration(string $eventId, string $userId): ?EventRegistrationResponseDTO;

    /**
     * Get a registration by ID.
     */
    public function find(string $registrationId): ?EventRegistrationResponseDTO;
}
