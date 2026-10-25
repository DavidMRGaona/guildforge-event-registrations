<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Listeners;

use Modules\EventRegistrations\Application\Services\RegistrationNotificationServiceInterface;
use Modules\EventRegistrations\Domain\Entities\EventRegistration;
use Modules\EventRegistrations\Domain\Events\UserUnregisteredFromEvent;
use Modules\EventRegistrations\Domain\Repositories\EventRegistrationRepositoryInterface;
use Modules\EventRegistrations\Domain\ValueObjects\EventRegistrationId;

/**
 * Confirms by email that the user's own cancellation went through.
 */
final readonly class SendRegistrationCancelledEmail
{
    public function __construct(
        private EventRegistrationRepositoryInterface $registrationRepository,
        private RegistrationNotificationServiceInterface $notificationService,
    ) {}

    public function handle(UserUnregisteredFromEvent $event): void
    {
        $registration = $this->registrationRepository->find(
            EventRegistrationId::fromString($event->registrationId)
        );

        if (! $registration instanceof EventRegistration) {
            return;
        }

        $this->notificationService->sendCancellationEmail($registration);
    }
}
