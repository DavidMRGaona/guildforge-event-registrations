<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\EventRegistrations\Application\Services\RegistrationNotificationServiceInterface;
use Modules\EventRegistrations\Domain\Events\UserRegisteredToEvent;
use Modules\EventRegistrations\Domain\Repositories\EventRegistrationRepositoryInterface;
use Modules\EventRegistrations\Domain\ValueObjects\EventRegistrationId;

final class SendUserRegisteredEmail implements ShouldQueue
{
    public function __construct(
        private readonly EventRegistrationRepositoryInterface $registrationRepository,
        private readonly RegistrationNotificationServiceInterface $notificationService,
    ) {}

    public function handle(UserRegisteredToEvent $event): void
    {
        $registration = $this->registrationRepository->find(
            EventRegistrationId::fromString($event->registrationId)
        );

        if ($registration === null) {
            return;
        }

        $this->notificationService->sendRegistrationEmail($registration);
        $this->notificationService->sendAdminNotification($registration);
    }
}
