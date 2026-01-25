<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\EventRegistrations\Application\Services\RegistrationNotificationServiceInterface;
use Modules\EventRegistrations\Domain\Events\WaitingListPromoted;
use Modules\EventRegistrations\Domain\Repositories\EventRegistrationRepositoryInterface;
use Modules\EventRegistrations\Domain\ValueObjects\EventRegistrationId;

final class SendWaitingListPromotedEmail implements ShouldQueue
{
    public function __construct(
        private readonly EventRegistrationRepositoryInterface $registrationRepository,
        private readonly RegistrationNotificationServiceInterface $notificationService,
    ) {}

    public function handle(WaitingListPromoted $event): void
    {
        $registration = $this->registrationRepository->find(
            EventRegistrationId::fromString($event->registrationId)
        );

        if ($registration === null) {
            return;
        }

        $this->notificationService->sendPromotionEmail($registration);
    }
}
