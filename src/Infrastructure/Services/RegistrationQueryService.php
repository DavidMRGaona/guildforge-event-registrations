<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Modules\EventRegistrations\Application\DTOs\EventRegistrationConfigDTO;
use Modules\EventRegistrations\Application\DTOs\EventRegistrationResponseDTO;
use Modules\EventRegistrations\Application\DTOs\RegistrationListItemDTO;
use Modules\EventRegistrations\Application\Services\RegistrationQueryServiceInterface;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Domain\Repositories\EventRegistrationConfigRepositoryInterface;
use Modules\EventRegistrations\Domain\Repositories\EventRegistrationRepositoryInterface;

final readonly class RegistrationQueryService implements RegistrationQueryServiceInterface
{
    public function __construct(
        private EventRegistrationRepositoryInterface $registrationRepository,
        private EventRegistrationConfigRepositoryInterface $configRepository,
    ) {}

    public function getEventStatus(string $eventId): EventRegistrationConfigDTO
    {
        $config = $this->configRepository->findByEventOrDefault($eventId);
        $currentParticipants = $this->registrationRepository->countConfirmedByEvent($eventId);
        $currentWaitingList = $this->registrationRepository->countWaitingListByEvent($eventId);

        return EventRegistrationConfigDTO::fromEntity(
            $config,
            $currentParticipants,
            $currentWaitingList,
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
            fn ($registration) => EventRegistrationResponseDTO::fromEntity($registration),
            $registrations
        );
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
                fn ($registration) => EventRegistrationResponseDTO::fromEntity($registration),
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
