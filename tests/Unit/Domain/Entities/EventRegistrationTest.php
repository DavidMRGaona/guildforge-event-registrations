<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Unit\Domain\Entities;

use Modules\EventRegistrations\Domain\Entities\EventRegistration;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Domain\Exceptions\CannotCancelRegistrationException;
use Modules\EventRegistrations\Domain\ValueObjects\EventRegistrationId;
use PHPUnit\Framework\TestCase;

final class EventRegistrationTest extends TestCase
{
    private function createRegistration(RegistrationState $state = RegistrationState::Pending): EventRegistration
    {
        return new EventRegistration(
            id: EventRegistrationId::generate(),
            eventId: 'event-123',
            userId: 'user-456',
            state: $state,
        );
    }

    public function test_it_creates_registration_with_required_data(): void
    {
        $id = EventRegistrationId::generate();
        $registration = new EventRegistration(
            id: $id,
            eventId: 'event-123',
            userId: 'user-456',
        );

        $this->assertEquals($id, $registration->id());
        $this->assertEquals('event-123', $registration->eventId());
        $this->assertEquals('user-456', $registration->userId());
        $this->assertEquals(RegistrationState::Pending, $registration->state());
        $this->assertNull($registration->position());
        $this->assertEmpty($registration->formData());
    }

    public function test_it_confirms_registration(): void
    {
        $registration = $this->createRegistration();

        $registration->confirm();

        $this->assertEquals(RegistrationState::Confirmed, $registration->state());
        $this->assertNotNull($registration->confirmedAt());
        $this->assertNull($registration->position());
    }

    public function test_it_moves_to_waiting_list(): void
    {
        $registration = $this->createRegistration();

        $registration->moveToWaitingList(5);

        $this->assertEquals(RegistrationState::WaitingList, $registration->state());
        $this->assertEquals(5, $registration->position());
    }

    public function test_it_cancels_registration(): void
    {
        $registration = $this->createRegistration(RegistrationState::Confirmed);

        $registration->cancel();

        $this->assertEquals(RegistrationState::Cancelled, $registration->state());
        $this->assertNotNull($registration->cancelledAt());
    }

    public function test_it_throws_when_cancelling_already_cancelled(): void
    {
        $registration = $this->createRegistration(RegistrationState::Cancelled);

        $this->expectException(CannotCancelRegistrationException::class);
        $registration->cancel();
    }

    public function test_it_throws_when_cancelling_rejected(): void
    {
        $registration = $this->createRegistration(RegistrationState::Rejected);

        $this->expectException(CannotCancelRegistrationException::class);
        $registration->cancel();
    }

    public function test_it_rejects_registration(): void
    {
        $registration = $this->createRegistration();

        $registration->reject();

        $this->assertEquals(RegistrationState::Rejected, $registration->state());
    }

    public function test_it_promotes_from_waiting_list(): void
    {
        $registration = $this->createRegistration(RegistrationState::WaitingList);
        $registration->moveToWaitingList(3);

        $registration->promoteFromWaitingList();

        $this->assertEquals(RegistrationState::Confirmed, $registration->state());
        $this->assertNull($registration->position());
        $this->assertNotNull($registration->confirmedAt());
    }

    public function test_promote_does_nothing_if_not_on_waiting_list(): void
    {
        $registration = $this->createRegistration(RegistrationState::Confirmed);

        $registration->promoteFromWaitingList();

        $this->assertEquals(RegistrationState::Confirmed, $registration->state());
    }

    public function test_it_updates_position(): void
    {
        $registration = $this->createRegistration(RegistrationState::WaitingList);
        $registration->moveToWaitingList(5);

        $registration->updatePosition(3);

        $this->assertEquals(3, $registration->position());
    }

    public function test_it_updates_notes(): void
    {
        $registration = $this->createRegistration();

        $registration->updateNotes('My notes');

        $this->assertEquals('My notes', $registration->notes());
    }

    public function test_it_updates_admin_notes(): void
    {
        $registration = $this->createRegistration();

        $registration->updateAdminNotes('Admin notes');

        $this->assertEquals('Admin notes', $registration->adminNotes());
    }

    public function test_it_updates_form_data(): void
    {
        $registration = $this->createRegistration();

        $registration->updateFormData(['field1' => 'value1']);

        $this->assertEquals(['field1' => 'value1'], $registration->formData());
    }

    public function test_is_active_returns_true_only_for_confirmed(): void
    {
        $confirmed = $this->createRegistration(RegistrationState::Confirmed);
        $pending = $this->createRegistration(RegistrationState::Pending);

        $this->assertTrue($confirmed->isActive());
        $this->assertFalse($pending->isActive());
    }

    public function test_is_on_waiting_list_returns_true_only_for_waiting_list(): void
    {
        $waiting = $this->createRegistration(RegistrationState::WaitingList);
        $confirmed = $this->createRegistration(RegistrationState::Confirmed);

        $this->assertTrue($waiting->isOnWaitingList());
        $this->assertFalse($confirmed->isOnWaitingList());
    }

    public function test_can_be_cancelled_returns_false_for_final_states(): void
    {
        $pending = $this->createRegistration(RegistrationState::Pending);
        $cancelled = $this->createRegistration(RegistrationState::Cancelled);

        $this->assertTrue($pending->canBeCancelled());
        $this->assertFalse($cancelled->canBeCancelled());
    }

    public function test_can_be_promoted_returns_true_only_for_waiting_list(): void
    {
        $waiting = $this->createRegistration(RegistrationState::WaitingList);
        $confirmed = $this->createRegistration(RegistrationState::Confirmed);

        $this->assertTrue($waiting->canBePromoted());
        $this->assertFalse($confirmed->canBePromoted());
    }
}
