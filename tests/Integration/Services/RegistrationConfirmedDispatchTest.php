<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Integration\Services;

use DateTimeImmutable;
use Illuminate\Support\Facades\Event;
use Modules\EventRegistrations\Application\DTOs\RegisterToEventDTO;
use Modules\EventRegistrations\Application\Services\MembershipCheckerInterface;
use Modules\EventRegistrations\Application\Services\PublishedEventCheckerInterface;
use Modules\EventRegistrations\Application\Services\WaitingListServiceInterface;
use Modules\EventRegistrations\Domain\Entities\EventRegistration;
use Modules\EventRegistrations\Domain\Entities\EventRegistrationConfig;
use Modules\EventRegistrations\Domain\Enums\ConfirmationSource;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Domain\Events\RegistrationConfirmed;
use Modules\EventRegistrations\Domain\Repositories\EventRegistrationConfigRepositoryInterface;
use Modules\EventRegistrations\Domain\Repositories\EventRegistrationRepositoryInterface;
use Modules\EventRegistrations\Domain\ValueObjects\EventRegistrationId;
use Modules\EventRegistrations\Infrastructure\Services\EventRegistrationService;
use Modules\EventRegistrations\Infrastructure\Services\WaitingListService;
use PHPUnit\Framework\MockObject\Stub;
use Tests\TestCase;

final class RegistrationConfirmedDispatchTest extends TestCase
{
    private EventRegistrationRepositoryInterface&Stub $registrationRepository;

    private EventRegistrationConfigRepositoryInterface&Stub $configRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registrationRepository = $this->createStub(EventRegistrationRepositoryInterface::class);
        $this->configRepository = $this->createStub(EventRegistrationConfigRepositoryInterface::class);

        Event::fake();
    }

    public function test_auto_confirmed_registration_dispatches_confirmation_from_registration(): void
    {
        $this->configRepository
            ->method('findByEventOrDefault')
            ->willReturn(new EventRegistrationConfig(
                eventId: 'event-1',
                requiresConfirmation: false,
                eventStartsAt: new DateTimeImmutable('+1 week'),
            ));

        $this->makeRegistrationService()->register(new RegisterToEventDTO(eventId: 'event-1', userId: 'user-1'));

        Event::assertDispatched(
            RegistrationConfirmed::class,
            fn (RegistrationConfirmed $event): bool => $event->source === ConfirmationSource::Registration,
        );
    }

    public function test_manual_confirmation_dispatches_manual_confirmation(): void
    {
        $registration = new EventRegistration(
            id: EventRegistrationId::generate(),
            eventId: 'event-1',
            userId: 'user-1',
            state: RegistrationState::Pending,
        );

        $this->registrationRepository->method('findOrFail')->willReturn($registration);

        $this->makeRegistrationService()->confirm($registration->id()->value);

        Event::assertDispatched(
            RegistrationConfirmed::class,
            fn (RegistrationConfirmed $event): bool => $event->source === ConfirmationSource::Manual,
        );
    }

    public function test_waiting_list_promotion_dispatches_confirmation_from_promotion(): void
    {
        $registration = new EventRegistration(
            id: EventRegistrationId::generate(),
            eventId: 'event-1',
            userId: 'user-1',
            state: RegistrationState::WaitingList,
            position: 1,
        );

        $this->registrationRepository->method('findFirstInWaitingList')->willReturn($registration);

        $this->configRepository
            ->method('findByEventOrDefault')
            ->willReturn(new EventRegistrationConfig(eventId: 'event-1', eventStartsAt: new DateTimeImmutable('+1 week')));

        (new WaitingListService($this->registrationRepository, $this->configRepository))->promoteNext('event-1');

        Event::assertDispatched(
            RegistrationConfirmed::class,
            fn (RegistrationConfirmed $event): bool => $event->source === ConfirmationSource::WaitingListPromotion,
        );
    }

    private function makeRegistrationService(): EventRegistrationService
    {
        return new EventRegistrationService(
            $this->registrationRepository,
            $this->configRepository,
            $this->createStub(WaitingListServiceInterface::class),
            $this->createStub(MembershipCheckerInterface::class),
            $this->publishedEvents(),
        );
    }

    private function publishedEvents(): PublishedEventCheckerInterface
    {
        $checker = $this->createStub(PublishedEventCheckerInterface::class);
        $checker->method('isPublished')->willReturn(true);

        return $checker;
    }
}
