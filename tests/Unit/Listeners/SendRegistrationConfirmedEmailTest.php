<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Unit\Listeners;

use DateTimeImmutable;
use Modules\EventRegistrations\Application\Services\RegistrationNotificationServiceInterface;
use Modules\EventRegistrations\Domain\Entities\EventRegistration;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Domain\Events\RegistrationConfirmed;
use Modules\EventRegistrations\Domain\Repositories\EventRegistrationRepositoryInterface;
use Modules\EventRegistrations\Domain\ValueObjects\EventRegistrationId;
use Modules\EventRegistrations\Listeners\SendRegistrationConfirmedEmail;
use PHPUnit\Framework\TestCase;

final class SendRegistrationConfirmedEmailTest extends TestCase
{
    public function test_sends_confirmation_email_when_confirmed_manually(): void
    {
        $registration = $this->confirmedRegistration();

        $repository = $this->createMock(EventRegistrationRepositoryInterface::class);
        $repository->method('find')->willReturn($registration);

        $notificationService = $this->createMock(RegistrationNotificationServiceInterface::class);
        $notificationService
            ->expects($this->once())
            ->method('sendConfirmationEmail')
            ->with($registration);

        $listener = new SendRegistrationConfirmedEmail($repository, $notificationService);
        $listener->handle($this->confirmedEvent($registration, automatic: false));
    }

    public function test_skips_confirmation_email_when_confirmed_automatically(): void
    {
        $registration = $this->confirmedRegistration();

        $repository = $this->createMock(EventRegistrationRepositoryInterface::class);
        $repository->method('find')->willReturn($registration);

        $notificationService = $this->createMock(RegistrationNotificationServiceInterface::class);
        $notificationService->expects($this->never())->method('sendConfirmationEmail');

        $listener = new SendRegistrationConfirmedEmail($repository, $notificationService);
        $listener->handle($this->confirmedEvent($registration, automatic: true));
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

    private function confirmedEvent(EventRegistration $registration, bool $automatic): RegistrationConfirmed
    {
        return new RegistrationConfirmed(
            registrationId: $registration->id()->value,
            eventId: $registration->eventId(),
            userId: $registration->userId(),
            occurredAt: new DateTimeImmutable,
            automatic: $automatic,
        );
    }
}
