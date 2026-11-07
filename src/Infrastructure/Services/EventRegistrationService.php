<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Infrastructure\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\EventRegistrations\Application\DTOs\BulkTransitionResultDTO;
use Modules\EventRegistrations\Application\DTOs\EventRegistrationResponseDTO;
use Modules\EventRegistrations\Application\DTOs\RegisterToEventDTO;
use Modules\EventRegistrations\Application\DTOs\UpdateRegistrationConfigDTO;
use Modules\EventRegistrations\Application\Services\EventRegistrationServiceInterface;
use Modules\EventRegistrations\Application\Services\MembershipCheckerInterface;
use Modules\EventRegistrations\Application\Services\PublishedEventCheckerInterface;
use Modules\EventRegistrations\Application\Services\WaitingListServiceInterface;
use Modules\EventRegistrations\Domain\Entities\EventRegistration;
use Modules\EventRegistrations\Domain\Entities\EventRegistrationConfig;
use Modules\EventRegistrations\Domain\Enums\ConfirmationSource;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Domain\Events\RegistrationConfirmed;
use Modules\EventRegistrations\Domain\Events\RegistrationRejected;
use Modules\EventRegistrations\Domain\Events\UserRegisteredToEvent;
use Modules\EventRegistrations\Domain\Events\UserUnregisteredFromEvent;
use Modules\EventRegistrations\Domain\Exceptions\AlreadyRegisteredException;
use Modules\EventRegistrations\Domain\Exceptions\CannotCancelRegistrationException;
use Modules\EventRegistrations\Domain\Exceptions\EventFullException;
use Modules\EventRegistrations\Domain\Exceptions\InvalidRegistrationTransitionException;
use Modules\EventRegistrations\Domain\Exceptions\MembersOnlyException;
use Modules\EventRegistrations\Domain\Exceptions\RegistrationClosedException;
use Modules\EventRegistrations\Domain\Exceptions\RegistrationNotFoundException;
use Modules\EventRegistrations\Domain\Exceptions\RegistrationRejectedException;
use Modules\EventRegistrations\Domain\Repositories\EventRegistrationConfigRepositoryInterface;
use Modules\EventRegistrations\Domain\Repositories\EventRegistrationRepositoryInterface;
use Modules\EventRegistrations\Domain\Services\Clock;
use Modules\EventRegistrations\Domain\ValueObjects\EventRegistrationId;

final readonly class EventRegistrationService implements EventRegistrationServiceInterface
{
    use EnsuresPublishedEvent;

    public function __construct(
        private EventRegistrationRepositoryInterface $registrationRepository,
        private EventRegistrationConfigRepositoryInterface $configRepository,
        private WaitingListServiceInterface $waitingListService,
        private MembershipCheckerInterface $membershipChecker,
        private PublishedEventCheckerInterface $publishedEvents,
        private Clock $clock,
    ) {}

    public function meetsMembersOnly(string $eventId, ?string $userId): bool
    {
        return $this->meetsMembersOnlyRequirement($this->configRepository->findByEventOrDefault($eventId), $userId);
    }

    public function register(RegisterToEventDTO $dto): EventRegistrationResponseDTO
    {
        $this->ensurePublished($dto->eventId);

        // Check if user is already registered
        $existing = $this->registrationRepository->findByUserAndEvent($dto->userId, $dto->eventId);
        if ($existing instanceof EventRegistration && ! $existing->state()->isFinal()) {
            throw AlreadyRegisteredException::userAlreadyRegistered($dto->eventId, $dto->userId);
        }

        // A cancelled registration may register again; a rejected one stays as the organisers left it
        if ($existing instanceof EventRegistration && $existing->state() === RegistrationState::Rejected) {
            throw RegistrationRejectedException::cannotRegisterAgain($dto->eventId, $dto->userId);
        }

        // Get config and validate registration is open
        $config = $this->configRepository->findByEventOrDefault($dto->eventId);
        $now = $this->clock->now();

        if (! $config->isOpen($now)) {
            // The start of the event closes registration whatever the configured dates say
            if ($config->hasEventStarted($now)) {
                throw RegistrationClosedException::eventStarted($dto->eventId);
            }
            if (! $config->isRegistrationEnabled()) {
                throw RegistrationClosedException::disabled($dto->eventId);
            }
            if ($config->registrationOpensAt() instanceof \DateTimeImmutable && $now < $config->registrationOpensAt()) {
                throw RegistrationClosedException::notYetOpen($dto->eventId);
            }
            throw RegistrationClosedException::alreadyClosed($dto->eventId);
        }

        // Members-only events, checked before the waiting list is considered
        if (! $this->meetsMembersOnlyRequirement($config, $dto->userId)) {
            throw MembersOnlyException::membersOnly($dto->eventId);
        }

        // Check capacity
        $currentConfirmed = $this->registrationRepository->countConfirmedByEvent($dto->eventId);

        // Determine initial state
        $state = $this->determineInitialState($config, $currentConfirmed);

        if (! $state instanceof RegistrationState) {
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

        // Reactivate an existing cancelled registration or create a new one
        if ($existing instanceof EventRegistration && $existing->state()->isFinal()) {
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
                source: ConfirmationSource::Registration,
            ));
        }

        return EventRegistrationResponseDTO::fromEntity($registration);
    }

    public function cancel(string $eventId, string $userId): void
    {
        $this->ensurePublished($eventId);

        $registration = $this->registrationRepository->findByUserAndEvent($userId, $eventId);

        if (! $registration instanceof EventRegistration) {
            throw RegistrationNotFoundException::forUserAndEvent($userId, $eventId);
        }

        // Check if cancellation deadline has passed
        $config = $this->configRepository->findByEventOrDefault($eventId);
        $now = $this->clock->now();

        // Users cannot leave once the event has started, whatever the deadline says
        if ($config->hasEventStarted($now)) {
            throw CannotCancelRegistrationException::eventStarted($eventId);
        }

        if (! $config->canCancelNow($now)) {
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

        // The state first: a cancelled registration says so, not that the event is full
        if (! $registration->state()->canBeConfirmed()) {
            throw InvalidRegistrationTransitionException::cannotConfirm($registration->id()->value, $registration->state());
        }

        if ($this->freeSpots($registration->eventId()) === 0) {
            throw EventFullException::noSpotsToConfirm($registration->eventId());
        }

        $wasWaiting = $registration->isOnWaitingList();
        $registration->confirm();
        $this->registrationRepository->save($registration);

        // Whoever was behind on the waiting list moves up
        if ($wasWaiting) {
            $this->waitingListService->recalculatePositions($registration->eventId());
        }

        $this->announceConfirmation($registration);

        return EventRegistrationResponseDTO::fromEntity($registration);
    }

    public function confirmMany(array $registrationIds): BulkTransitionResultDTO
    {
        $registrations = array_map(
            fn (string $registrationId): EventRegistration => $this->registrationRepository->findOrFail(
                EventRegistrationId::fromString($registrationId)
            ),
            $registrationIds,
        );

        $confirmable = array_values(array_filter(
            $registrations,
            fn (EventRegistration $registration): bool => $registration->state()->canBeConfirmed(),
        ));
        $skippedInvalid = count($registrations) - count($confirmable);

        // The waiting list first, by position; then pending registrations, oldest first
        usort($confirmable, fn (EventRegistration $a, EventRegistration $b): int => $this->confirmationOrder($a) <=> $this->confirmationOrder($b));

        $confirmed = 0;
        $skippedFull = 0;

        DB::transaction(function () use ($confirmable, &$confirmed, &$skippedFull): void {
            /** @var array<string, int|null> $freeSpots null when the event has no limit */
            $freeSpots = [];
            $waitingEvents = [];

            foreach ($confirmable as $registration) {
                $eventId = $registration->eventId();

                if (! array_key_exists($eventId, $freeSpots)) {
                    $freeSpots[$eventId] = $this->freeSpots($eventId);
                }

                if ($freeSpots[$eventId] === 0) {
                    $skippedFull++;

                    continue;
                }

                if ($registration->isOnWaitingList()) {
                    $waitingEvents[$eventId] = true;
                }

                $registration->confirm();
                $this->registrationRepository->save($registration);
                $this->announceConfirmation($registration);

                if ($freeSpots[$eventId] !== null) {
                    $freeSpots[$eventId]--;
                }
                $confirmed++;
            }

            // Whoever was behind on the waiting list moves up
            foreach (array_keys($waitingEvents) as $eventId) {
                $this->waitingListService->recalculatePositions((string) $eventId);
            }
        });

        return new BulkTransitionResultDTO(changed: $confirmed, skippedInvalid: $skippedInvalid, skippedFull: $skippedFull);
    }

    public function reject(string $registrationId): EventRegistrationResponseDTO
    {
        $registration = $this->registrationRepository->findOrFail(
            EventRegistrationId::fromString($registrationId)
        );

        // Already rejected: nothing changes and nobody is told again
        if ($registration->state() === RegistrationState::Rejected) {
            return EventRegistrationResponseDTO::fromEntity($registration);
        }

        // A cancelled registration cannot be rejected: the domain refuses it
        $registration->reject();
        $this->registrationRepository->save($registration);
        $this->announceRejection($registration);

        // A rejected confirmed registration frees a spot for the waiting list
        $this->waitingListService->fillFreeSpots($registration->eventId());

        return EventRegistrationResponseDTO::fromEntity($registration);
    }

    public function rejectMany(array $registrationIds): BulkTransitionResultDTO
    {
        $eventIds = [];
        $rejected = 0;
        $skipped = 0;

        // All or none: a failure halfway leaves every selected registration as it was
        DB::transaction(function () use ($registrationIds, &$eventIds, &$rejected, &$skipped): void {
            foreach ($registrationIds as $registrationId) {
                $registration = $this->registrationRepository->findOrFail(
                    EventRegistrationId::fromString($registrationId)
                );

                // Cancelled and already rejected registrations stay as they are
                if (! $registration->state()->canBeRejected()) {
                    $skipped++;

                    continue;
                }

                $registration->reject();
                $this->registrationRepository->save($registration);
                $this->announceRejection($registration);

                $eventIds[$registration->eventId()] = true;
                $rejected++;
            }
        });

        // Fill only after every selected registration is out, once per event
        foreach (array_keys($eventIds) as $eventId) {
            $this->waitingListService->fillFreeSpots((string) $eventId);
        }

        return new BulkTransitionResultDTO(changed: $rejected, skippedInvalid: $skipped);
    }

    public function moveToWaitingList(string $registrationId): EventRegistrationResponseDTO
    {
        $registration = $this->registrationRepository->findOrFail(
            EventRegistrationId::fromString($registrationId)
        );

        $position = $this->registrationRepository->getNextWaitingListPosition($registration->eventId());
        $registration->moveToWaitingList($position);
        $this->registrationRepository->save($registration);

        // The freed spot goes to whoever was already waiting; the moved registration stays on the list
        $this->waitingListService->fillFreeSpots($registration->eventId(), $registration->id()->value);

        return EventRegistrationResponseDTO::fromEntity($registration);
    }

    public function updateConfig(UpdateRegistrationConfigDTO $dto): void
    {
        // Only what the change provides, on top of what is stored (or the defaults of a new
        // config): a form that does not show a field, or hides it, never blanks it
        $config = $this->configRepository->findByEventOrDefault($dto->eventId);

        $setters = [
            'registration_enabled' => fn () => $config->setRegistrationEnabled($dto->registrationEnabled),
            'max_participants' => fn () => $config->setMaxParticipants($dto->maxParticipants),
            'waiting_list_enabled' => fn () => $config->setWaitingListEnabled($dto->waitingListEnabled),
            'max_waiting_list' => fn () => $config->setMaxWaitingList($dto->maxWaitingList),
            'registration_opens_at' => fn () => $config->setRegistrationOpensAt($dto->registrationOpensAt),
            'registration_closes_at' => fn () => $config->setRegistrationClosesAt($dto->registrationClosesAt),
            'cancellation_deadline' => fn () => $config->setCancellationDeadline($dto->cancellationDeadline),
            'requires_confirmation' => fn () => $config->setRequiresConfirmation($dto->requiresConfirmation),
            'requires_payment' => fn () => $config->setRequiresPayment($dto->requiresPayment),
            'members_only' => fn () => $config->setMembersOnly($dto->membersOnly),
            'custom_fields' => fn () => $config->setCustomFields($dto->customFields),
            'confirmation_message' => fn () => $config->setConfirmationMessage($dto->confirmationMessage),
            'notification_email' => fn () => $config->setNotificationEmail($dto->notificationEmail),
        ];

        foreach ($setters as $field => $set) {
            if ($dto->provides($field)) {
                $set();
            }
        }

        $this->configRepository->save($config);
    }

    public function getUserRegistration(string $eventId, string $userId): ?EventRegistrationResponseDTO
    {
        $this->ensurePublished($eventId);

        $registration = $this->registrationRepository->findByUserAndEvent($userId, $eventId);

        return $registration instanceof EventRegistration
            ? EventRegistrationResponseDTO::fromEntity($registration)
            : null;
    }

    public function find(string $registrationId): ?EventRegistrationResponseDTO
    {
        $registration = $this->registrationRepository->find(
            EventRegistrationId::fromString($registrationId)
        );

        return $registration instanceof EventRegistration
            ? EventRegistrationResponseDTO::fromEntity($registration)
            : null;
    }

    /**
     * Free spots of the event for one more confirmation, or null when it has no limit.
     */
    private function freeSpots(string $eventId): ?int
    {
        $limit = $this->configRepository->findByEventOrDefault($eventId)->participantLimit();

        if ($limit === null) {
            return null;
        }

        return max(0, $limit - $this->registrationRepository->countConfirmedByEvent($eventId));
    }

    /**
     * Sort key of a bulk confirmation: the waiting list first, by position, then pending
     * registrations by the time they registered.
     *
     * @return array{int, int, int|float, string}
     */
    private function confirmationOrder(EventRegistration $registration): array
    {
        return [
            $registration->isOnWaitingList() ? 0 : 1,
            $registration->position() ?? PHP_INT_MAX,
            $registration->createdAt()?->getTimestamp() ?? PHP_INT_MAX,
            $registration->id()->value,
        ];
    }

    /**
     * Tell the person their registration was confirmed by the organisers, once the change is
     * committed.
     */
    private function announceConfirmation(EventRegistration $registration): void
    {
        $event = RegistrationConfirmed::create(
            registrationId: $registration->id()->value,
            eventId: $registration->eventId(),
            userId: $registration->userId(),
        );

        DB::afterCommit(static fn () => Event::dispatch($event));
    }

    /**
     * Tell the person their registration was rejected, once the change is committed: a
     * rolled back rejection never sends the email.
     */
    private function announceRejection(EventRegistration $registration): void
    {
        $event = RegistrationRejected::create(
            registrationId: $registration->id()->value,
            eventId: $registration->eventId(),
            userId: $registration->userId(),
        );

        DB::afterCommit(static fn () => Event::dispatch($event));
    }

    private function determineInitialState(
        EventRegistrationConfig $config,
        int $currentConfirmed,
    ): ?RegistrationState {
        $maxParticipants = $config->participantLimit();

        // No limit (empty or 0) - can be confirmed (or pending if requires confirmation)
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

    /**
     * Members-only events accept association members only; other events accept everyone.
     */
    private function meetsMembersOnlyRequirement(EventRegistrationConfig $config, ?string $userId): bool
    {
        if (! $config->isMembersOnly()) {
            return true;
        }

        return $userId !== null && $this->membershipChecker->isMember($userId);
    }
}
