<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Integration\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\EventRegistrations\Domain\Entities\EventRegistration;
use Modules\EventRegistrations\Domain\Entities\EventRegistrationConfig;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Domain\Events\UserUnregisteredFromEvent;
use Modules\EventRegistrations\Domain\Repositories\EventRegistrationConfigRepositoryInterface;
use Modules\EventRegistrations\Domain\Repositories\EventRegistrationRepositoryInterface;
use Modules\EventRegistrations\Domain\ValueObjects\EventRegistrationId;
use Modules\EventRegistrations\Infrastructure\Services\WaitingListService;
use Modules\EventRegistrations\Listeners\PromoteFromWaitingListOnCancellation;
use Tests\TestCase;

final class WaitingListPromotionTest extends TestCase
{
    use RefreshDatabase;

    private EventRegistrationRepositoryInterface $registrationRepository;

    private EventRegistrationConfigRepositoryInterface $configRepository;

    private WaitingListService $waitingListService;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip if module tables don't exist
        if (! $this->app->bound(EventRegistrationRepositoryInterface::class)) {
            $this->markTestSkipped('Event registrations module not loaded');
        }

        $this->registrationRepository = $this->app->make(EventRegistrationRepositoryInterface::class);
        $this->configRepository = $this->app->make(EventRegistrationConfigRepositoryInterface::class);
        $this->waitingListService = $this->app->make(WaitingListService::class);
    }

    public function test_promotes_next_user_when_participant_cancels(): void
    {
        $eventId = 'test-event-'.uniqid();

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

        // Simulate cancellation event
        $event = UserUnregisteredFromEvent::create(
            registrationId: $confirmed1->id()->value,
            eventId: $eventId,
            userId: 'user-1',
            previousState: RegistrationState::Confirmed,
        );

        // Handle the event
        $listener = $this->app->make(PromoteFromWaitingListOnCancellation::class);
        $listener->handle($event);

        // Verify waiting list user was promoted
        $promotedRegistration = $this->registrationRepository->findByUserAndEvent('user-3', $eventId);

        $this->assertNotNull($promotedRegistration);
        $this->assertEquals(RegistrationState::Confirmed, $promotedRegistration->state());
        $this->assertNull($promotedRegistration->position());
        $this->assertNotNull($promotedRegistration->confirmedAt());
    }

    public function test_does_not_promote_if_waiting_list_empty(): void
    {
        $eventId = 'test-event-'.uniqid();

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
            userId: 'user-1',
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
        $eventId = 'test-event-'.uniqid();

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
        $promoted = $this->registrationRepository->findByUserAndEvent('user-2', $eventId);
        $this->assertEquals(RegistrationState::Confirmed, $promoted->state());

        // Verify remaining positions were recalculated
        $remaining2 = $this->registrationRepository->findByUserAndEvent('user-3', $eventId);
        $remaining3 = $this->registrationRepository->findByUserAndEvent('user-4', $eventId);

        $this->assertEquals(1, $remaining2->position()); // Was 2, now 1
        $this->assertEquals(2, $remaining3->position()); // Was 3, now 2
    }

    public function test_does_not_promote_when_cancellation_was_from_waiting_list(): void
    {
        $eventId = 'test-event-'.uniqid();

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
            userId: 'user-2',
            previousState: RegistrationState::WaitingList, // Key: was on waiting list
        );

        $listener = $this->app->make(PromoteFromWaitingListOnCancellation::class);
        $listener->handle($event);

        // Verify user-3 was NOT promoted (still on waiting list)
        $user3 = $this->registrationRepository->findByUserAndEvent('user-3', $eventId);
        $this->assertEquals(RegistrationState::WaitingList, $user3->state());
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
            userId: $userId,
            state: $state,
            position: $position,
        );
    }
}
