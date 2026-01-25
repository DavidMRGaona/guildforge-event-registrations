<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Listeners;

use Modules\EventRegistrations\Application\Services\WaitingListServiceInterface;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Domain\Events\UserUnregisteredFromEvent;
use Modules\EventRegistrations\Domain\Repositories\EventRegistrationConfigRepositoryInterface;
use Modules\EventRegistrations\Domain\Repositories\EventRegistrationRepositoryInterface;

final readonly class PromoteFromWaitingListOnCancellation
{
    public function __construct(
        private EventRegistrationRepositoryInterface $registrationRepository,
        private EventRegistrationConfigRepositoryInterface $configRepository,
        private WaitingListServiceInterface $waitingListService,
    ) {}

    public function handle(UserUnregisteredFromEvent $event): void
    {
        // Only promote if the cancelled registration was confirmed
        if ($event->previousState !== RegistrationState::Confirmed) {
            return;
        }

        // Check if there's capacity to promote someone
        $config = $this->configRepository->findByEventOrDefault($event->eventId);

        if (! $config->isWaitingListEnabled()) {
            return;
        }

        $currentConfirmed = $this->registrationRepository->countConfirmedByEvent($event->eventId);
        $maxParticipants = $config->maxParticipants();

        // If there's now a spot available, promote the next person
        if ($maxParticipants === null || $currentConfirmed < $maxParticipants) {
            $this->waitingListService->promoteNext($event->eventId);
        }
    }
}
