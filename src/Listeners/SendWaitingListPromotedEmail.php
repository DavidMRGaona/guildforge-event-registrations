<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\EventRegistrations\Application\Services\RegistrationNotificationServiceInterface;
use Modules\EventRegistrations\Domain\Entities\EventRegistration;
use Modules\EventRegistrations\Domain\Events\WaitingListPromoted;
use Modules\EventRegistrations\Domain\Repositories\EventRegistrationRepositoryInterface;
use Modules\EventRegistrations\Domain\ValueObjects\EventRegistrationId;

final readonly class SendWaitingListPromotedEmail implements ShouldQueue
{
    public function __construct(
        private EventRegistrationRepositoryInterface $registrationRepository,
        private RegistrationNotificationServiceInterface $notificationService,
    ) {}

    public function handle(WaitingListPromoted $event): void
    {
        $registration = $this->registrationRepository->find(
            EventRegistrationId::fromString($event->registrationId)
        );

        if (! $registration instanceof EventRegistration) {
            return;
        }

        $this->notificationService->sendPromotionEmail($registration);
    }
}
