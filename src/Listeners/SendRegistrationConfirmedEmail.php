<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\EventRegistrations\Application\Services\RegistrationNotificationServiceInterface;
use Modules\EventRegistrations\Domain\Entities\EventRegistration;
use Modules\EventRegistrations\Domain\Events\RegistrationConfirmed;
use Modules\EventRegistrations\Domain\Repositories\EventRegistrationRepositoryInterface;
use Modules\EventRegistrations\Domain\ValueObjects\EventRegistrationId;

final readonly class SendRegistrationConfirmedEmail implements ShouldQueue
{
    public function __construct(
        private EventRegistrationRepositoryInterface $registrationRepository,
        private RegistrationNotificationServiceInterface $notificationService,
    ) {}

    public function handle(RegistrationConfirmed $event): void
    {
        if ($this->notificationService->confirmationAlreadyAnnounced($event->source)) {
            return;
        }

        $registration = $this->registrationRepository->find(
            EventRegistrationId::fromString($event->registrationId)
        );

        if (! $registration instanceof EventRegistration) {
            return;
        }

        $this->notificationService->sendConfirmationEmail($registration);
    }
}
