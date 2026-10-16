<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Listeners;

use Modules\EventRegistrations\Application\Services\WaitingListServiceInterface;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Domain\Events\UserUnregisteredFromEvent;

final readonly class PromoteFromWaitingListOnCancellation
{
    public function __construct(
        private WaitingListServiceInterface $waitingListService,
    ) {}

    public function handle(UserUnregisteredFromEvent $event): void
    {
        // Someone leaving the waiting list frees no spot, but the people behind move up
        if ($event->previousState === RegistrationState::WaitingList) {
            $this->waitingListService->recalculatePositions($event->eventId);

            return;
        }

        // Only a cancelled confirmed registration frees a spot
        if ($event->previousState !== RegistrationState::Confirmed) {
            return;
        }

        $this->waitingListService->fillFreeSpots($event->eventId);
    }
}
