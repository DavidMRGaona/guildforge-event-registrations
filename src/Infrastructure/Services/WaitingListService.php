<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Infrastructure\Services;

use Illuminate\Support\Facades\Event;
use Modules\EventRegistrations\Application\DTOs\EventRegistrationResponseDTO;
use Modules\EventRegistrations\Application\Services\WaitingListServiceInterface;
use Modules\EventRegistrations\Domain\Entities\EventRegistration;
use Modules\EventRegistrations\Domain\Enums\ConfirmationSource;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Domain\Events\RegistrationConfirmed;
use Modules\EventRegistrations\Domain\Events\UserRegisteredToEvent;
use Modules\EventRegistrations\Domain\Events\WaitingListPromoted;
use Modules\EventRegistrations\Domain\Repositories\EventRegistrationConfigRepositoryInterface;
use Modules\EventRegistrations\Domain\Repositories\EventRegistrationRepositoryInterface;
use Modules\EventRegistrations\Domain\ValueObjects\EventRegistrationId;

final readonly class WaitingListService implements WaitingListServiceInterface
{
    public function __construct(
        private EventRegistrationRepositoryInterface $registrationRepository,
        private EventRegistrationConfigRepositoryInterface $configRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $formData
     */
    public function addToWaitingList(
        string $eventId,
        string $userId,
        array $formData = [],
        ?EventRegistration $existingRegistration = null,
    ): EventRegistrationResponseDTO {
        $position = $this->registrationRepository->getNextWaitingListPosition($eventId);

        // Reactivate existing cancelled/rejected registration or create new one
        if ($existingRegistration !== null && $existingRegistration->state()->isFinal()) {
            $registration = $existingRegistration;
            $registration->reactivate(RegistrationState::WaitingList, $formData);
            $registration->updatePosition($position);
        } else {
            $registration = new EventRegistration(
                id: EventRegistrationId::generate(),
                eventId: $eventId,
                userId: $userId,
                state: RegistrationState::WaitingList,
                position: $position,
                formData: $formData,
            );
        }

        $this->registrationRepository->save($registration);

        // Dispatch domain event
        Event::dispatch(UserRegisteredToEvent::create(
            registrationId: $registration->id()->value,
            eventId: $registration->eventId(),
            userId: $registration->userId(),
            state: $registration->state(),
            position: $position,
        ));

        return EventRegistrationResponseDTO::fromEntity($registration);
    }

    public function promoteNext(string $eventId, ?string $excludeRegistrationId = null): ?EventRegistrationResponseDTO
    {
        // Nobody is promoted once the event has started, whoever freed the spot
        if ($this->configRepository->findByEventOrDefault($eventId)->hasEventStarted()) {
            return null;
        }

        return $this->promoteFirstInWaitingList($eventId, $excludeRegistrationId);
    }

    public function fillFreeSpots(string $eventId, ?string $excludeRegistrationId = null): int
    {
        $promoted = $this->promoteWhileThereAreFreeSpots($eventId, $excludeRegistrationId);

        // Someone may have left the list with no promotion (rejected, deleted, moved): close the gap
        $this->recalculatePositions($eventId);

        return $promoted;
    }

    private function promoteWhileThereAreFreeSpots(string $eventId, ?string $excludeRegistrationId): int
    {
        $config = $this->configRepository->findByEventOrDefault($eventId);

        // No waiting list, or the event has started: nobody is promoted
        if (! $config->isWaitingListEnabled() || $config->hasEventStarted()) {
            return 0;
        }

        $maxParticipants = $config->maxParticipants();
        // Each promotion takes one person off the list: never more rounds than people waiting
        $rounds = $this->registrationRepository->countWaitingListByEvent($eventId);
        $promoted = 0;

        while (
            $promoted < $rounds
            && ($maxParticipants === null || $this->registrationRepository->countConfirmedByEvent($eventId) < $maxParticipants)
        ) {
            if ($this->promoteFirstInWaitingList($eventId, $excludeRegistrationId) === null) {
                break;
            }

            $promoted++;
        }

        return $promoted;
    }

    private function promoteFirstInWaitingList(string $eventId, ?string $excludeRegistrationId = null): ?EventRegistrationResponseDTO
    {
        $registration = $this->registrationRepository->findFirstInWaitingList($eventId, $excludeRegistrationId);

        if ($registration === null) {
            return null;
        }

        $previousPosition = $registration->position() ?? 1;

        // Promote to confirmed
        $registration->promoteFromWaitingList();
        $this->registrationRepository->save($registration);

        // Recalculate positions for remaining waiting list
        $this->recalculatePositions($eventId);

        // Dispatch events
        Event::dispatch(WaitingListPromoted::create(
            registrationId: $registration->id()->value,
            eventId: $registration->eventId(),
            userId: $registration->userId(),
            previousPosition: $previousPosition,
        ));

        Event::dispatch(RegistrationConfirmed::create(
            registrationId: $registration->id()->value,
            eventId: $registration->eventId(),
            userId: $registration->userId(),
            source: ConfirmationSource::WaitingListPromotion,
        ));

        return EventRegistrationResponseDTO::fromEntity($registration);
    }

    public function getPosition(string $eventId, string $userId): ?int
    {
        $registration = $this->registrationRepository->findByUserAndEvent($userId, $eventId);

        if ($registration === null || $registration->state() !== RegistrationState::WaitingList) {
            return null;
        }

        return $registration->position();
    }

    /**
     * @return array<EventRegistrationResponseDTO>
     */
    public function getWaitingList(string $eventId): array
    {
        $registrations = $this->registrationRepository->findWaitingListOrdered($eventId);

        return array_map(
            fn ($registration) => EventRegistrationResponseDTO::fromEntity($registration),
            $registrations
        );
    }

    public function recalculatePositions(string $eventId): void
    {
        $waitingList = $this->registrationRepository->findWaitingListOrdered($eventId);

        $position = 1;
        foreach ($waitingList as $registration) {
            if ($registration->position() !== $position) {
                $registration->updatePosition($position);
                $this->registrationRepository->save($registration);
            }
            $position++;
        }
    }
}
