<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Integration\Services;

use DateTimeImmutable;
use Illuminate\Support\Facades\Event;
use Modules\EventRegistrations\Application\DTOs\RegisterToEventDTO;
use Modules\EventRegistrations\Application\Services\MembershipCheckerInterface;
use Modules\EventRegistrations\Application\Services\WaitingListServiceInterface;
use Modules\EventRegistrations\Domain\Entities\EventRegistrationConfig;
use Modules\EventRegistrations\Domain\Exceptions\MembersOnlyException;
use Modules\EventRegistrations\Domain\Repositories\EventRegistrationConfigRepositoryInterface;
use Modules\EventRegistrations\Domain\Repositories\EventRegistrationRepositoryInterface;
use Modules\EventRegistrations\Infrastructure\Services\EventRegistrationService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use Tests\TestCase;

final class MembersOnlyRegistrationTest extends TestCase
{
    private EventRegistrationRepositoryInterface&MockObject $registrationRepository;

    private EventRegistrationConfigRepositoryInterface&Stub $configRepository;

    private WaitingListServiceInterface $waitingListService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registrationRepository = $this->createMock(EventRegistrationRepositoryInterface::class);
        $this->configRepository = $this->createStub(EventRegistrationConfigRepositoryInterface::class);
        $this->waitingListService = $this->createStub(WaitingListServiceInterface::class);

        Event::fake();
    }

    public function test_a_non_member_cannot_register_to_a_members_only_event(): void
    {
        $this->givenConfig(new EventRegistrationConfig(eventId: 'event-1', eventStartsAt: new DateTimeImmutable('+1 week'), membersOnly: true));
        $this->registrationRepository->expects($this->never())->method('save');

        $this->expectException(MembersOnlyException::class);

        $this->makeService($this->checker(isMember: false))
            ->register(new RegisterToEventDTO(eventId: 'event-1', userId: 'user-1'));
    }

    public function test_a_non_member_cannot_join_the_waiting_list_of_a_members_only_event(): void
    {
        $this->givenConfig(new EventRegistrationConfig(
            eventId: 'event-1',
            eventStartsAt: new DateTimeImmutable('+1 week'),
            maxParticipants: 1,
            waitingListEnabled: true,
            membersOnly: true,
        ));
        $this->registrationRepository->method('countConfirmedByEvent')->willReturn(1);
        $this->registrationRepository->expects($this->never())->method('save');
        $waitingListService = $this->createMock(WaitingListServiceInterface::class);
        $waitingListService->expects($this->never())->method('addToWaitingList');
        $this->waitingListService = $waitingListService;

        $this->expectException(MembersOnlyException::class);

        $this->makeService($this->checker(isMember: false))
            ->register(new RegisterToEventDTO(eventId: 'event-1', userId: 'user-1'));
    }

    public function test_a_member_can_register_to_a_members_only_event(): void
    {
        $this->givenConfig(new EventRegistrationConfig(eventId: 'event-1', eventStartsAt: new DateTimeImmutable('+1 week'), membersOnly: true));
        $this->registrationRepository->expects($this->once())->method('save');

        $response = $this->makeService($this->checker(isMember: true))
            ->register(new RegisterToEventDTO(eventId: 'event-1', userId: 'user-1'));

        $this->assertSame('user-1', $response->userId);
    }

    public function test_membership_is_not_checked_when_the_event_is_open_to_everyone(): void
    {
        $this->givenConfig(new EventRegistrationConfig(eventId: 'event-1', eventStartsAt: new DateTimeImmutable('+1 week'), membersOnly: false));
        $this->registrationRepository->expects($this->once())->method('save');

        $checker = $this->createMock(MembershipCheckerInterface::class);
        $checker->expects($this->never())->method('isMember');

        $this->makeService($checker)->register(new RegisterToEventDTO(eventId: 'event-1', userId: 'user-1'));
    }

    public function test_a_member_meets_the_members_only_requirement(): void
    {
        $this->givenConfig(new EventRegistrationConfig(eventId: 'event-1', eventStartsAt: new DateTimeImmutable('+1 week'), membersOnly: true));
        $this->registrationRepository->expects($this->never())->method('save');

        $this->assertTrue($this->makeService($this->checker(isMember: true))->meetsMembersOnly('event-1', 'user-1'));
    }

    public function test_a_non_member_does_not_meet_the_members_only_requirement(): void
    {
        $this->givenConfig(new EventRegistrationConfig(eventId: 'event-1', eventStartsAt: new DateTimeImmutable('+1 week'), membersOnly: true));
        $this->registrationRepository->expects($this->never())->method('save');

        $this->assertFalse($this->makeService($this->checker(isMember: false))->meetsMembersOnly('event-1', 'user-1'));
    }

    public function test_a_guest_does_not_meet_the_members_only_requirement(): void
    {
        $this->givenConfig(new EventRegistrationConfig(eventId: 'event-1', eventStartsAt: new DateTimeImmutable('+1 week'), membersOnly: true));
        $this->registrationRepository->expects($this->never())->method('save');
        $checker = $this->createMock(MembershipCheckerInterface::class);
        $checker->expects($this->never())->method('isMember');

        $this->assertFalse($this->makeService($checker)->meetsMembersOnly('event-1', null));
    }

    public function test_everyone_meets_the_requirement_of_an_event_open_to_everyone(): void
    {
        $this->givenConfig(new EventRegistrationConfig(eventId: 'event-1', eventStartsAt: new DateTimeImmutable('+1 week'), membersOnly: false));
        $this->registrationRepository->expects($this->never())->method('save');
        $checker = $this->createMock(MembershipCheckerInterface::class);
        $checker->expects($this->never())->method('isMember');

        $service = $this->makeService($checker);

        $this->assertTrue($service->meetsMembersOnly('event-1', 'user-1'));
        $this->assertTrue($service->meetsMembersOnly('event-1', null));
    }

    private function givenConfig(EventRegistrationConfig $config): void
    {
        $this->configRepository->method('findByEventOrDefault')->willReturn($config);
    }

    private function checker(bool $isMember): MembershipCheckerInterface
    {
        $checker = $this->createStub(MembershipCheckerInterface::class);
        $checker->method('isMember')->willReturn($isMember);

        return $checker;
    }

    private function makeService(MembershipCheckerInterface $checker): EventRegistrationService
    {
        return new EventRegistrationService(
            $this->registrationRepository,
            $this->configRepository,
            $this->waitingListService,
            $checker,
        );
    }
}
