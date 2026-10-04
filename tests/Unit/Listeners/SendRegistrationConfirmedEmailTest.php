<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Unit\Listeners;

use DateTimeImmutable;
use Modules\EventRegistrations\Application\Services\RegistrationNotificationServiceInterface;
use Modules\EventRegistrations\Domain\Entities\EventRegistration;
use Modules\EventRegistrations\Domain\Enums\ConfirmationSource;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Domain\Events\RegistrationConfirmed;
use Modules\EventRegistrations\Domain\Repositories\EventRegistrationRepositoryInterface;
use Modules\EventRegistrations\Domain\ValueObjects\EventRegistrationId;
use Modules\EventRegistrations\Listeners\SendRegistrationConfirmedEmail;
use PHPUnit\Framework\TestCase;

final class SendRegistrationConfirmedEmailTest extends TestCase
{
    public function test_sends_confirmation_email_when_not_announced_by_another_email(): void
    {
        $registration = $this->confirmedRegistration();

        $repository = $this->createMock(EventRegistrationRepositoryInterface::class);
        $repository->method('find')->willReturn($registration);

        $notificationService = $this->createMock(RegistrationNotificationServiceInterface::class);
        $notificationService
            ->method('confirmationAlreadyAnnounced')
            ->with(ConfirmationSource::Manual)
            ->willReturn(false);
        $notificationService
            ->expects($this->once())
            ->method('sendConfirmationEmail')
            ->with($registration);

        $listener = new SendRegistrationConfirmedEmail($repository, $notificationService);
        $listener->handle($this->confirmedEvent($registration, ConfirmationSource::Manual));
    }

    public function test_skips_confirmation_email_when_already_announced(): void
    {
        $registration = $this->confirmedRegistration();

        $repository = $this->createMock(EventRegistrationRepositoryInterface::class);
        $repository->method('find')->willReturn($registration);

        $notificationService = $this->createMock(RegistrationNotificationServiceInterface::class);
        $notificationService
            ->method('confirmationAlreadyAnnounced')
            ->with(ConfirmationSource::Registration)
            ->willReturn(true);
        $notificationService->expects($this->never())->method('sendConfirmationEmail');

        $listener = new SendRegistrationConfirmedEmail($repository, $notificationService);
        $listener->handle($this->confirmedEvent($registration, ConfirmationSource::Registration));
    }

    private function confirmedRegistration(): EventRegistration
    {
        return new EventRegistration(
            id: EventRegistrationId::generate(),
            eventId: 'event-1',
            userId: 'user-1',
            state: RegistrationState::Confirmed,
        );
    }

    private function confirmedEvent(EventRegistration $registration, ConfirmationSource $source): RegistrationConfirmed
    {
        return new RegistrationConfirmed(
            registrationId: $registration->id()->value,
            eventId: $registration->eventId(),
            userId: $registration->userId(),
            occurredAt: new DateTimeImmutable,
            source: $source,
        );
    }
}
