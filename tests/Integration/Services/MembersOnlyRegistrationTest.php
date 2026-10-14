<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Integration\Services;

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
        $this->givenConfig(new EventRegistrationConfig(eventId: 'event-1', membersOnly: true));
        $this->registrationRepository->expects($this->never())->method('save');

        $this->expectException(MembersOnlyException::class);

        $this->makeService($this->checker(isMember: false))
            ->register(new RegisterToEventDTO(eventId: 'event-1', userId: 'user-1'));
    }

    public function test_a_non_member_cannot_join_the_waiting_list_of_a_members_only_event(): void
    {
        $this->givenConfig(new EventRegistrationConfig(
            eventId: 'event-1',
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
        $this->givenConfig(new EventRegistrationConfig(eventId: 'event-1', membersOnly: true));
        $this->registrationRepository->expects($this->once())->method('save');

        $response = $this->makeService($this->checker(isMember: true))
            ->register(new RegisterToEventDTO(eventId: 'event-1', userId: 'user-1'));

        $this->assertSame('user-1', $response->userId);
    }

    public function test_membership_is_not_checked_when_the_event_is_open_to_everyone(): void
    {
        $this->givenConfig(new EventRegistrationConfig(eventId: 'event-1', membersOnly: false));
        $this->registrationRepository->expects($this->once())->method('save');

        $checker = $this->createMock(MembershipCheckerInterface::class);
        $checker->expects($this->never())->method('isMember');

        $this->makeService($checker)->register(new RegisterToEventDTO(eventId: 'event-1', userId: 'user-1'));
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
