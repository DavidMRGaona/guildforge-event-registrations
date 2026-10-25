<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Integration\Services;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\Notification;
use Modules\EventRegistrations\Domain\Entities\EventRegistration;
use Modules\EventRegistrations\Domain\Entities\EventRegistrationConfig;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Domain\Events\UserUnregisteredFromEvent;
use Modules\EventRegistrations\Domain\Repositories\EventRegistrationConfigRepositoryInterface;
use Modules\EventRegistrations\Domain\Repositories\EventRegistrationRepositoryInterface;
use Modules\EventRegistrations\Domain\Services\Clock;
use Modules\EventRegistrations\Domain\ValueObjects\EventRegistrationId;
use Modules\EventRegistrations\Infrastructure\Services\WaitingListService;
use Modules\EventRegistrations\Listeners\PromoteFromWaitingListOnCancellation;
use Modules\EventRegistrations\Tests\Support\FixedClock;
use Tests\Support\Modules\ModuleTestCase;

final class WaitingListPromotionTest extends ModuleTestCase
{
    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    /** @var array<string, string> */
    private array $users = [];

    private EventRegistrationRepositoryInterface $registrationRepository;

    private EventRegistrationConfigRepositoryInterface $configRepository;

    private WaitingListService $waitingListService;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        // Away from the nights the clocks change (RegistrationAcrossDaylightSavingTest)
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00'));

        foreach (['user-1', 'user-2', 'user-3', 'user-4'] as $alias) {
            $this->users[$alias] = UserModel::factory()->create()->id;
        }

        $this->registrationRepository = $this->app->make(EventRegistrationRepositoryInterface::class);
        $this->configRepository = $this->app->make(EventRegistrationConfigRepositoryInterface::class);
        $this->waitingListService = $this->app->make(WaitingListService::class);
    }

    public function test_promotes_next_user_when_participant_cancels(): void
    {
        $eventId = $this->upcomingEventId();

        // Create event config with max 2 participants
        $config = new EventRegistrationConfig(
            eventId: $eventId,
            maxParticipants: 2,
            waitingListEnabled: true,
        );
        $this->configRepository->save($config);

        // Create 2 confirmed registrations
        $confirmed1 = $this->createRegistration($eventId, 'user-1', RegistrationState::Confirmed);
        $confirmed2 = $this->createRegistration($eventId, 'user-2', RegistrationState::Confirmed);

        // Create 1 waiting list registration
        $waiting = $this->createRegistration($eventId, 'user-3', RegistrationState::WaitingList, 1);

        // Save all registrations
        $this->registrationRepository->save($confirmed1);
        $this->registrationRepository->save($confirmed2);
        $this->registrationRepository->save($waiting);

        // Cancel as EventRegistrationService::cancel() does before it dispatches the event
        $confirmed1->cancel();
        $this->registrationRepository->save($confirmed1);

        // Simulate cancellation event
        $event = UserUnregisteredFromEvent::create(
            registrationId: $confirmed1->id()->value,
            eventId: $eventId,
            userId: $this->users['user-1'],
            previousState: RegistrationState::Confirmed,
        );

        // Handle the event
        $listener = $this->app->make(PromoteFromWaitingListOnCancellation::class);
        $listener->handle($event);

        // Verify waiting list user was promoted
        $promotedRegistration = $this->registrationRepository->findByUserAndEvent($this->users['user-3'], $eventId);

        $this->assertNotNull($promotedRegistration);
        $this->assertEquals(RegistrationState::Confirmed, $promotedRegistration->state());
        $this->assertNull($promotedRegistration->position());
        $this->assertNotNull($promotedRegistration->confirmedAt());
    }

    public function test_does_not_promote_if_waiting_list_empty(): void
    {
        $eventId = $this->upcomingEventId();

        // Create event config
        $config = new EventRegistrationConfig(
            eventId: $eventId,
            maxParticipants: 2,
            waitingListEnabled: true,
        );
        $this->configRepository->save($config);

        // Create 2 confirmed registrations, no waiting list
        $confirmed1 = $this->createRegistration($eventId, 'user-1', RegistrationState::Confirmed);
        $confirmed2 = $this->createRegistration($eventId, 'user-2', RegistrationState::Confirmed);

        $this->registrationRepository->save($confirmed1);
        $this->registrationRepository->save($confirmed2);

        // Count before
        $confirmedBefore = $this->registrationRepository->countConfirmedByEvent($eventId);
        $waitingBefore = $this->registrationRepository->countWaitingListByEvent($eventId);

        // Simulate cancellation
        $event = UserUnregisteredFromEvent::create(
            registrationId: $confirmed1->id()->value,
            eventId: $eventId,
            userId: $this->users['user-1'],
            previousState: RegistrationState::Confirmed,
        );

        $listener = $this->app->make(PromoteFromWaitingListOnCancellation::class);
        $listener->handle($event);

        // Verify counts - confirmed should still be 2 (no promotion happened)
        $confirmedAfter = $this->registrationRepository->countConfirmedByEvent($eventId);
        $waitingAfter = $this->registrationRepository->countWaitingListByEvent($eventId);

        $this->assertEquals(2, $confirmedBefore);
        $this->assertEquals(0, $waitingBefore);
        $this->assertEquals(2, $confirmedAfter); // Still 2 because we didn't actually cancel in repo
        $this->assertEquals(0, $waitingAfter);
    }

    public function test_updates_waiting_list_positions_after_promotion(): void
    {
        $eventId = $this->upcomingEventId();

        // Create event config
        $config = new EventRegistrationConfig(
            eventId: $eventId,
            maxParticipants: 1,
            waitingListEnabled: true,
        );
        $this->configRepository->save($config);

        // Create confirmed registration
        $confirmed = $this->createRegistration($eventId, 'user-1', RegistrationState::Confirmed);
        $this->registrationRepository->save($confirmed);

        // Create waiting list with positions 1, 2, 3
        $waiting1 = $this->createRegistration($eventId, 'user-2', RegistrationState::WaitingList, 1);
        $waiting2 = $this->createRegistration($eventId, 'user-3', RegistrationState::WaitingList, 2);
        $waiting3 = $this->createRegistration($eventId, 'user-4', RegistrationState::WaitingList, 3);

        $this->registrationRepository->save($waiting1);
        $this->registrationRepository->save($waiting2);
        $this->registrationRepository->save($waiting3);

        // Promote user-2 (position 1)
        $this->waitingListService->promoteNext($eventId);

        // Verify promoted user
        $promoted = $this->registrationRepository->findByUserAndEvent($this->users['user-2'], $eventId);
        $this->assertEquals(RegistrationState::Confirmed, $promoted->state());

        // Verify remaining positions were recalculated
        $remaining2 = $this->registrationRepository->findByUserAndEvent($this->users['user-3'], $eventId);
        $remaining3 = $this->registrationRepository->findByUserAndEvent($this->users['user-4'], $eventId);

        $this->assertEquals(1, $remaining2->position()); // Was 2, now 1
        $this->assertEquals(2, $remaining3->position()); // Was 3, now 2
    }

    public function test_does_not_promote_when_cancellation_was_from_waiting_list(): void
    {
        $eventId = $this->upcomingEventId();

        // Create event config
        $config = new EventRegistrationConfig(
            eventId: $eventId,
            maxParticipants: 2,
            waitingListEnabled: true,
        );
        $this->configRepository->save($config);

        // Create registrations
        $confirmed = $this->createRegistration($eventId, 'user-1', RegistrationState::Confirmed);
        $waiting1 = $this->createRegistration($eventId, 'user-2', RegistrationState::WaitingList, 1);
        $waiting2 = $this->createRegistration($eventId, 'user-3', RegistrationState::WaitingList, 2);

        $this->registrationRepository->save($confirmed);
        $this->registrationRepository->save($waiting1);
        $this->registrationRepository->save($waiting2);

        // Simulate cancellation from waiting list (not confirmed)
        $event = UserUnregisteredFromEvent::create(
            registrationId: $waiting1->id()->value,
            eventId: $eventId,
            userId: $this->users['user-2'],
            previousState: RegistrationState::WaitingList, // Key: was on waiting list
        );

        $listener = $this->app->make(PromoteFromWaitingListOnCancellation::class);
        $listener->handle($event);

        // Verify user-3 was NOT promoted (still on waiting list)
        $user3 = $this->registrationRepository->findByUserAndEvent($this->users['user-3'], $eventId);
        $this->assertEquals(RegistrationState::WaitingList, $user3->state());
    }

    public function test_does_not_promote_when_a_spot_is_freed_after_the_event_started(): void
    {
        $eventId = $this->startedEventId();

        $this->configRepository->save(new EventRegistrationConfig(
            eventId: $eventId,
            maxParticipants: 2,
            waitingListEnabled: true,
        ));

        $confirmed1 = $this->createRegistration($eventId, 'user-1', RegistrationState::Confirmed);
        $confirmed2 = $this->createRegistration($eventId, 'user-2', RegistrationState::Confirmed);
        $waiting = $this->createRegistration($eventId, 'user-3', RegistrationState::WaitingList, 1);
        $this->registrationRepository->save($confirmed1);
        $this->registrationRepository->save($confirmed2);
        $this->registrationRepository->save($waiting);

        $confirmed1->cancel();
        $this->registrationRepository->save($confirmed1);

        $this->app->make(PromoteFromWaitingListOnCancellation::class)->handle(UserUnregisteredFromEvent::create(
            registrationId: $confirmed1->id()->value,
            eventId: $eventId,
            userId: $this->users['user-1'],
            previousState: RegistrationState::Confirmed,
        ));

        $stillWaiting = $this->registrationRepository->findByUserAndEvent($this->users['user-3'], $eventId);
        $this->assertNotNull($stillWaiting);
        $this->assertSame(RegistrationState::WaitingList, $stillWaiting->state());
        $this->assertSame(1, $stillWaiting->position());
    }

    public function test_promote_next_promotes_nobody_once_the_event_has_started(): void
    {
        // Whoever frees the spot, a user or the staff, nobody joins after the start
        $eventId = $this->startedEventId();

        $this->configRepository->save(new EventRegistrationConfig(
            eventId: $eventId,
            maxParticipants: 1,
            waitingListEnabled: true,
        ));

        $waiting = $this->createRegistration($eventId, 'user-2', RegistrationState::WaitingList, 1);
        $this->registrationRepository->save($waiting);

        $this->assertNull($this->waitingListService->promoteNext($eventId));

        $stillWaiting = $this->registrationRepository->findByUserAndEvent($this->users['user-2'], $eventId);
        $this->assertNotNull($stillWaiting);
        $this->assertSame(RegistrationState::WaitingList, $stillWaiting->state());
    }

    public function test_fill_free_spots_promotes_as_many_as_there_are_free_spots(): void
    {
        $eventId = $this->upcomingEventId();
        $this->configRepository->save(new EventRegistrationConfig(eventId: $eventId, maxParticipants: 3, waitingListEnabled: true));
        $this->registrationRepository->save($this->createRegistration($eventId, 'user-1', RegistrationState::Confirmed));
        foreach (['user-2', 'user-3', 'user-4'] as $position => $user) {
            $this->registrationRepository->save($this->createRegistration($eventId, $user, RegistrationState::WaitingList, $position + 1));
        }

        $this->assertSame(2, $this->waitingListService->fillFreeSpots($eventId));

        $this->assertSame(3, $this->registrationRepository->countConfirmedByEvent($eventId));
        $last = $this->registrationRepository->findByUserAndEvent($this->users['user-4'], $eventId);
        $this->assertSame(RegistrationState::WaitingList, $last?->state());
        $this->assertSame(1, $last?->position());
    }

    public function test_fill_free_spots_stops_when_nobody_is_left_waiting(): void
    {
        $eventId = $this->upcomingEventId();
        $this->configRepository->save(new EventRegistrationConfig(eventId: $eventId, maxParticipants: null, waitingListEnabled: true));
        $this->registrationRepository->save($this->createRegistration($eventId, 'user-1', RegistrationState::WaitingList, 1));
        $this->registrationRepository->save($this->createRegistration($eventId, 'user-2', RegistrationState::WaitingList, 2));

        $this->assertSame(2, $this->waitingListService->fillFreeSpots($eventId));
        $this->assertSame(0, $this->registrationRepository->countWaitingListByEvent($eventId));
    }

    public function test_fill_free_spots_promotes_nobody_once_the_event_has_started(): void
    {
        $eventId = $this->startedEventId();
        $this->configRepository->save(new EventRegistrationConfig(eventId: $eventId, maxParticipants: 2, waitingListEnabled: true));
        $this->registrationRepository->save($this->createRegistration($eventId, 'user-1', RegistrationState::WaitingList, 1));

        $this->assertSame(0, $this->waitingListService->fillFreeSpots($eventId));
    }

    public function test_fill_free_spots_skips_the_excluded_registration(): void
    {
        $eventId = $this->upcomingEventId();
        $this->configRepository->save(new EventRegistrationConfig(eventId: $eventId, maxParticipants: null, waitingListEnabled: true));
        $excluded = $this->createRegistration($eventId, 'user-1', RegistrationState::WaitingList, 1);
        $this->registrationRepository->save($excluded);
        $this->registrationRepository->save($this->createRegistration($eventId, 'user-2', RegistrationState::WaitingList, 2));

        $this->assertSame(1, $this->waitingListService->fillFreeSpots($eventId, $excluded->id()->value));

        $this->assertSame(RegistrationState::WaitingList, $this->registrationRepository->findByUserAndEvent($this->users['user-1'], $eventId)?->state());
        $this->assertSame(RegistrationState::Confirmed, $this->registrationRepository->findByUserAndEvent($this->users['user-2'], $eventId)?->state());
    }

    public function test_fill_free_spots_promotes_nobody_when_the_waiting_list_is_disabled(): void
    {
        $eventId = $this->upcomingEventId();
        $this->configRepository->save(new EventRegistrationConfig(eventId: $eventId, maxParticipants: 2, waitingListEnabled: false));
        $this->registrationRepository->save($this->createRegistration($eventId, 'user-1', RegistrationState::WaitingList, 1));

        $this->assertSame(0, $this->waitingListService->fillFreeSpots($eventId));
    }

    public function test_promote_next_promotes_during_the_repeated_hour_before_an_event_after_the_change(): void
    {
        $waitingListService = $this->serviceWithClockAt('2026-10-25T00:15:00Z');
        $eventId = $this->eventIdStartingAt('2026-10-25 03:30');
        $this->configRepository->save(new EventRegistrationConfig(eventId: $eventId, maxParticipants: 1, waitingListEnabled: true));
        $this->registrationRepository->save($this->createRegistration($eventId, 'user-1', RegistrationState::WaitingList, 1));

        $this->assertNotNull($waitingListService->promoteNext($eventId));
    }

    public function test_fill_free_spots_promotes_during_the_repeated_hour_before_an_event_after_the_change(): void
    {
        $waitingListService = $this->serviceWithClockAt('2026-10-25T00:15:00Z');
        $eventId = $this->eventIdStartingAt('2026-10-25 03:30');
        $this->configRepository->save(new EventRegistrationConfig(eventId: $eventId, maxParticipants: 1, waitingListEnabled: true));
        $this->registrationRepository->save($this->createRegistration($eventId, 'user-1', RegistrationState::WaitingList, 1));

        $this->assertSame(1, $waitingListService->fillFreeSpots($eventId));
    }

    public function test_nobody_is_promoted_during_the_repeated_hour_once_an_event_before_the_change_has_started(): void
    {
        $waitingListService = $this->serviceWithClockAt('2026-10-25T00:15:00Z');
        $eventId = $this->eventIdStartingAt('2026-10-25 01:30');
        $this->configRepository->save(new EventRegistrationConfig(eventId: $eventId, maxParticipants: 1, waitingListEnabled: true));
        $this->registrationRepository->save($this->createRegistration($eventId, 'user-1', RegistrationState::WaitingList, 1));

        $this->assertNull($waitingListService->promoteNext($eventId));
        $this->assertSame(0, $waitingListService->fillFreeSpots($eventId));
    }

    private function serviceWithClockAt(string $instant): WaitingListService
    {
        $this->app->instance(Clock::class, FixedClock::at($instant));

        return $this->app->make(WaitingListService::class);
    }

    private function eventIdStartingAt(string $madridWallTime): string
    {
        $startsAt = new DateTimeImmutable($madridWallTime, new DateTimeZone('Europe/Madrid'));

        return EventModel::factory()->create([
            'start_date' => $startsAt,
            'end_date' => $startsAt->modify('+4 hours'),
        ])->id;
    }

    /**
     * An event a week after the pinned now. The factory's default start follows the real clock,
     * which travelTo() does not move, so it could fall before the pinned now.
     */
    private function upcomingEventId(): string
    {
        return EventModel::factory()->create([
            'start_date' => now()->addWeek(),
            'end_date' => now()->addWeek()->addHours(4),
        ])->id;
    }

    private function startedEventId(): string
    {
        return EventModel::factory()->create([
            'start_date' => now()->subMinute(),
            'end_date' => now()->addHours(3),
        ])->id;
    }

    private function createRegistration(
        string $eventId,
        string $userId,
        RegistrationState $state,
        ?int $position = null,
    ): EventRegistration {
        return new EventRegistration(
            id: EventRegistrationId::generate(),
            eventId: $eventId,
            userId: $this->users[$userId],
            state: $state,
            position: $position,
        );
    }
}
