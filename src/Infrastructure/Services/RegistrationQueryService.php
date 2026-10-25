<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Modules\EventRegistrations\Application\DTOs\EventRegistrationConfigDTO;
use Modules\EventRegistrations\Application\DTOs\EventRegistrationResponseDTO;
use Modules\EventRegistrations\Application\DTOs\RegisteredEventDTO;
use Modules\EventRegistrations\Application\DTOs\RegistrationListItemDTO;
use Modules\EventRegistrations\Application\DTOs\UserRegistrationDTO;
use Modules\EventRegistrations\Application\Services\PublishedEventCheckerInterface;
use Modules\EventRegistrations\Application\Services\RegistrationQueryServiceInterface;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Domain\Repositories\EventRegistrationConfigRepositoryInterface;
use Modules\EventRegistrations\Domain\Repositories\EventRegistrationRepositoryInterface;
use Modules\EventRegistrations\Domain\Services\Clock;

final readonly class RegistrationQueryService implements RegistrationQueryServiceInterface
{
    use EnsuresPublishedEvent;

    public function __construct(
        private EventRegistrationRepositoryInterface $registrationRepository,
        private EventRegistrationConfigRepositoryInterface $configRepository,
        private PublishedEventCheckerInterface $publishedEvents,
        private Clock $clock,
    ) {}

    public function getEventStatus(string $eventId): EventRegistrationConfigDTO
    {
        $this->ensurePublished($eventId);

        $config = $this->configRepository->findByEventOrDefault($eventId);
        $currentParticipants = $this->registrationRepository->countConfirmedByEvent($eventId);
        $currentWaitingList = $this->registrationRepository->countWaitingListByEvent($eventId);

        return EventRegistrationConfigDTO::fromEntity(
            $config,
            $currentParticipants,
            $currentWaitingList,
            $this->clock->now(),
        );
    }

    /**
     * @return array<RegistrationListItemDTO>
     */
    public function getEventRegistrations(string $eventId): array
    {
        $registrations = $this->registrationRepository->findByEvent($eventId);

        return array_map(function ($registration) {
            $user = UserModel::find($registration->userId());

            return RegistrationListItemDTO::fromEntity(
                $registration,
                $user?->name,
                $user?->email,
            );
        }, $registrations);
    }

    /**
     * @return array<RegistrationListItemDTO>
     */
    public function getConfirmedRegistrations(string $eventId): array
    {
        $registrations = $this->registrationRepository->findByEventAndState(
            $eventId,
            RegistrationState::Confirmed
        );

        return array_map(function ($registration) {
            $user = UserModel::find($registration->userId());

            return RegistrationListItemDTO::fromEntity(
                $registration,
                $user?->name,
                $user?->email,
            );
        }, $registrations);
    }

    /**
     * @return array<RegistrationListItemDTO>
     */
    public function getWaitingList(string $eventId): array
    {
        $registrations = $this->registrationRepository->findWaitingListOrdered($eventId);

        return array_map(function ($registration) {
            $user = UserModel::find($registration->userId());

            return RegistrationListItemDTO::fromEntity(
                $registration,
                $user?->name,
                $user?->email,
            );
        }, $registrations);
    }

    /**
     * @return array<EventRegistrationResponseDTO>
     */
    public function getUserRegistrations(string $userId): array
    {
        $registrations = $this->registrationRepository->findByUser($userId);

        return array_map(
            EventRegistrationResponseDTO::fromEntity(...),
            $registrations
        );
    }

    /**
     * Two queries whatever the number of registrations: the registrations, then their
     * published events in one go.
     *
     * @return array<UserRegistrationDTO>
     */
    public function getUserRegistrationsWithEvents(string $userId): array
    {
        $registrations = $this->registrationRepository->findByUser($userId);

        if ($registrations === []) {
            return [];
        }

        $eventIds = array_values(array_unique(array_map(
            fn ($registration): string => $registration->eventId(),
            $registrations,
        )));

        $events = EventModel::query()
            ->whereIn('id', $eventIds)
            ->where('is_published', true)
            ->get(['id', 'title', 'slug', 'start_date', 'end_date', 'location'])
            ->keyBy('id');

        return array_map(function ($registration) use ($events): UserRegistrationDTO {
            /** @var EventModel|null $event */
            $event = $events->get($registration->eventId());

            return new UserRegistrationDTO(
                registration: EventRegistrationResponseDTO::fromEntity($registration),
                event: $event === null ? null : new RegisteredEventDTO(
                    id: (string) $event->id,
                    title: $event->title,
                    slug: $event->slug,
                    startDate: $event->start_date->format('c'),
                    endDate: $event->end_date->format('c'),
                    location: $event->location,
                    url: route('events.show', ['slug' => $event->slug], absolute: false),
                ),
            );
        }, $registrations);
    }

    /**
     * @return array<EventRegistrationResponseDTO>
     */
    public function getUserUpcomingRegistrations(string $userId): array
    {
        // This would need to join with events table to filter by event date
        // For now, return all non-cancelled registrations
        $registrations = $this->registrationRepository->findByUser($userId);

        return array_filter(
            array_map(
                EventRegistrationResponseDTO::fromEntity(...),
                $registrations
            ),
            fn ($dto) => ! $dto->state->isFinal()
        );
    }

    public function countConfirmed(string $eventId): int
    {
        return $this->registrationRepository->countConfirmedByEvent($eventId);
    }

    public function countWaitingList(string $eventId): int
    {
        return $this->registrationRepository->countWaitingListByEvent($eventId);
    }
}
