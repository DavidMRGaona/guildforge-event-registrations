<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Infrastructure\Services;

use Illuminate\Support\Facades\Event;
use Modules\EventRegistrations\Application\DTOs\EventRegistrationResponseDTO;
use Modules\EventRegistrations\Application\DTOs\RegisterToEventDTO;
use Modules\EventRegistrations\Application\DTOs\UpdateRegistrationConfigDTO;
use Modules\EventRegistrations\Application\Services\EventRegistrationServiceInterface;
use Modules\EventRegistrations\Application\Services\WaitingListServiceInterface;
use Modules\EventRegistrations\Domain\Entities\EventRegistration;
use Modules\EventRegistrations\Domain\Entities\EventRegistrationConfig;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Domain\Events\RegistrationConfirmed;
use Modules\EventRegistrations\Domain\Events\UserRegisteredToEvent;
use Modules\EventRegistrations\Domain\Events\UserUnregisteredFromEvent;
use Modules\EventRegistrations\Domain\Exceptions\AlreadyRegisteredException;
use Modules\EventRegistrations\Domain\Exceptions\CannotCancelRegistrationException;
use Modules\EventRegistrations\Domain\Exceptions\EventFullException;
use Modules\EventRegistrations\Domain\Exceptions\RegistrationClosedException;
use Modules\EventRegistrations\Domain\Exceptions\RegistrationNotFoundException;
use Modules\EventRegistrations\Domain\Repositories\EventRegistrationConfigRepositoryInterface;
use Modules\EventRegistrations\Domain\Repositories\EventRegistrationRepositoryInterface;
use Modules\EventRegistrations\Domain\ValueObjects\EventRegistrationId;

final readonly class EventRegistrationService implements EventRegistrationServiceInterface
{
    public function __construct(
        private EventRegistrationRepositoryInterface $registrationRepository,
        private EventRegistrationConfigRepositoryInterface $configRepository,
        private WaitingListServiceInterface $waitingListService,
    ) {}

    public function register(RegisterToEventDTO $dto): EventRegistrationResponseDTO
    {
        // Check if user is already registered
        $existing = $this->registrationRepository->findByUserAndEvent($dto->userId, $dto->eventId);
        if ($existing !== null && ! $existing->state()->isFinal()) {
            throw AlreadyRegisteredException::userAlreadyRegistered($dto->eventId, $dto->userId);
        }

        // Get config and validate registration is open
        $config = $this->configRepository->findByEventOrDefault($dto->eventId);

        if (! $config->isOpen()) {
            if (! $config->isRegistrationEnabled()) {
                throw RegistrationClosedException::disabled($dto->eventId);
            }
            if ($config->registrationOpensAt() !== null && new \DateTimeImmutable < $config->registrationOpensAt()) {
                throw RegistrationClosedException::notYetOpen($dto->eventId);
            }
            throw RegistrationClosedException::alreadyClosed($dto->eventId);
        }

        // Check capacity
        $currentConfirmed = $this->registrationRepository->countConfirmedByEvent($dto->eventId);
        $maxParticipants = $config->maxParticipants();

        // Determine initial state
        $state = $this->determineInitialState($config, $currentConfirmed);

        if ($state === null) {
            // Both main list and waiting list are full
            throw EventFullException::noSpotsAvailable($dto->eventId);
        }

        if ($state === RegistrationState::WaitingList) {
            // Check waiting list capacity
            $currentWaiting = $this->registrationRepository->countWaitingListByEvent($dto->eventId);
            if ($config->hasWaitingListLimit() && $currentWaiting >= $config->maxWaitingList()) {
                throw EventFullException::waitingListFull($dto->eventId);
            }

            return $this->waitingListService->addToWaitingList(
                $dto->eventId,
                $dto->userId,
                $dto->formData,
                $existing, // Pass existing registration to reactivate if cancelled
            );
        }

        // Reactivate existing cancelled/rejected registration or create new one
        if ($existing !== null && $existing->state()->isFinal()) {
            $registration = $existing;
            $registration->reactivate($state, $dto->formData, $dto->notes);
        } else {
            $registration = new EventRegistration(
                id: EventRegistrationId::generate(),
                eventId: $dto->eventId,
                userId: $dto->userId,
                state: $state,
                formData: $dto->formData,
                notes: $dto->notes,
            );
        }

        // If auto-confirm (no manual confirmation required)
        if (! $config->requiresConfirmation() && $registration->state() === RegistrationState::Pending) {
            $registration->confirm();
        }

        $this->registrationRepository->save($registration);

        // Dispatch domain event
        Event::dispatch(UserRegisteredToEvent::create(
            registrationId: $registration->id()->value,
            eventId: $registration->eventId(),
            userId: $registration->userId(),
            state: $registration->state(),
        ));

        // If confirmed, dispatch confirmation event
        if ($registration->state() === RegistrationState::Confirmed) {
            Event::dispatch(RegistrationConfirmed::create(
                registrationId: $registration->id()->value,
                eventId: $registration->eventId(),
                userId: $registration->userId(),
            ));
        }

        return EventRegistrationResponseDTO::fromEntity($registration);
    }

    public function cancel(string $eventId, string $userId): void
    {
        $registration = $this->registrationRepository->findByUserAndEvent($userId, $eventId);

        if ($registration === null) {
            throw RegistrationNotFoundException::forUserAndEvent($userId, $eventId);
        }

        // Check if cancellation deadline has passed
        $config = $this->configRepository->findByEventOrDefault($eventId);
        if (! $config->canCancelNow()) {
            throw CannotCancelRegistrationException::deadlinePassed($eventId);
        }

        $previousState = $registration->state();
        $registration->cancel();

        $this->registrationRepository->save($registration);

        // Dispatch domain event
        Event::dispatch(UserUnregisteredFromEvent::create(
            registrationId: $registration->id()->value,
            eventId: $registration->eventId(),
            userId: $registration->userId(),
            previousState: $previousState,
        ));
    }

    public function confirm(string $registrationId): EventRegistrationResponseDTO
    {
        $registration = $this->registrationRepository->findOrFail(
            EventRegistrationId::fromString($registrationId)
        );

        $registration->confirm();
        $this->registrationRepository->save($registration);

        // Dispatch confirmation event
        Event::dispatch(RegistrationConfirmed::create(
            registrationId: $registration->id()->value,
            eventId: $registration->eventId(),
            userId: $registration->userId(),
        ));

        return EventRegistrationResponseDTO::fromEntity($registration);
    }

    public function reject(string $registrationId): EventRegistrationResponseDTO
    {
        $registration = $this->registrationRepository->findOrFail(
            EventRegistrationId::fromString($registrationId)
        );

        $registration->reject();
        $this->registrationRepository->save($registration);

        return EventRegistrationResponseDTO::fromEntity($registration);
    }

    public function moveToWaitingList(string $registrationId): EventRegistrationResponseDTO
    {
        $registration = $this->registrationRepository->findOrFail(
            EventRegistrationId::fromString($registrationId)
        );

        $position = $this->registrationRepository->getNextWaitingListPosition($registration->eventId());
        $registration->moveToWaitingList($position);
        $this->registrationRepository->save($registration);

        return EventRegistrationResponseDTO::fromEntity($registration);
    }

    public function updateConfig(UpdateRegistrationConfigDTO $dto): void
    {
        $config = new EventRegistrationConfig(
            eventId: $dto->eventId,
            registrationEnabled: $dto->registrationEnabled,
            maxParticipants: $dto->maxParticipants,
            waitingListEnabled: $dto->waitingListEnabled,
            maxWaitingList: $dto->maxWaitingList,
            registrationOpensAt: $dto->registrationOpensAt,
            registrationClosesAt: $dto->registrationClosesAt,
            cancellationDeadline: $dto->cancellationDeadline,
            requiresConfirmation: $dto->requiresConfirmation,
            requiresPayment: $dto->requiresPayment,
            membersOnly: $dto->membersOnly,
            customFields: $dto->customFields,
            confirmationMessage: $dto->confirmationMessage,
        );

        $this->configRepository->save($config);
    }

    public function getUserRegistration(string $eventId, string $userId): ?EventRegistrationResponseDTO
    {
        $registration = $this->registrationRepository->findByUserAndEvent($userId, $eventId);

        return $registration !== null
            ? EventRegistrationResponseDTO::fromEntity($registration)
            : null;
    }

    public function find(string $registrationId): ?EventRegistrationResponseDTO
    {
        $registration = $this->registrationRepository->find(
            EventRegistrationId::fromString($registrationId)
        );

        return $registration !== null
            ? EventRegistrationResponseDTO::fromEntity($registration)
            : null;
    }

    private function determineInitialState(
        EventRegistrationConfig $config,
        int $currentConfirmed,
    ): ?RegistrationState {
        $maxParticipants = $config->maxParticipants();

        // No limit - can be confirmed (or pending if requires confirmation)
        if ($maxParticipants === null) {
            return $config->requiresConfirmation()
                ? RegistrationState::Pending
                : RegistrationState::Pending; // Will be auto-confirmed after creation
        }

        // Has capacity - can be confirmed
        if ($currentConfirmed < $maxParticipants) {
            return $config->requiresConfirmation()
                ? RegistrationState::Pending
                : RegistrationState::Pending;
        }

        // Full - check waiting list
        if ($config->isWaitingListEnabled()) {
            return RegistrationState::WaitingList;
        }

        // Full and no waiting list
        return null;
    }
}
