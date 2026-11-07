<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Unit\Domain\Enums;

use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use PHPUnit\Framework\TestCase;

final class RegistrationStateTest extends TestCase
{
    public function test_it_has_expected_cases(): void
    {
        $cases = RegistrationState::cases();

        $this->assertContains(RegistrationState::Pending, $cases);
        $this->assertContains(RegistrationState::Confirmed, $cases);
        $this->assertContains(RegistrationState::WaitingList, $cases);
        $this->assertContains(RegistrationState::Cancelled, $cases);
        $this->assertContains(RegistrationState::Rejected, $cases);
    }

    public function test_confirmed_state_is_active(): void
    {
        $this->assertTrue(RegistrationState::Confirmed->isActive());
        $this->assertFalse(RegistrationState::Pending->isActive());
        $this->assertFalse(RegistrationState::WaitingList->isActive());
        $this->assertFalse(RegistrationState::Cancelled->isActive());
        $this->assertFalse(RegistrationState::Rejected->isActive());
    }

    public function test_waiting_states_are_identified(): void
    {
        $this->assertTrue(RegistrationState::Pending->isWaiting());
        $this->assertTrue(RegistrationState::WaitingList->isWaiting());
        $this->assertFalse(RegistrationState::Confirmed->isWaiting());
        $this->assertFalse(RegistrationState::Cancelled->isWaiting());
        $this->assertFalse(RegistrationState::Rejected->isWaiting());
    }

    public function test_final_states_are_identified(): void
    {
        $this->assertTrue(RegistrationState::Cancelled->isFinal());
        $this->assertTrue(RegistrationState::Rejected->isFinal());
        $this->assertFalse(RegistrationState::Pending->isFinal());
        $this->assertFalse(RegistrationState::Confirmed->isFinal());
        $this->assertFalse(RegistrationState::WaitingList->isFinal());
    }

    public function test_can_be_cancelled_for_non_final_states(): void
    {
        $this->assertTrue(RegistrationState::Pending->canBeCancelled());
        $this->assertTrue(RegistrationState::Confirmed->canBeCancelled());
        $this->assertTrue(RegistrationState::WaitingList->canBeCancelled());
        $this->assertFalse(RegistrationState::Cancelled->canBeCancelled());
        $this->assertFalse(RegistrationState::Rejected->canBeCancelled());
    }

    public function test_can_be_promoted_only_from_waiting_list(): void
    {
        $this->assertTrue(RegistrationState::WaitingList->canBePromoted());
        $this->assertFalse(RegistrationState::Pending->canBePromoted());
        $this->assertFalse(RegistrationState::Confirmed->canBePromoted());
        $this->assertFalse(RegistrationState::Cancelled->canBePromoted());
        $this->assertFalse(RegistrationState::Rejected->canBePromoted());
    }

    public function test_values_returns_string_values(): void
    {
        $values = RegistrationState::values();

        $this->assertContains('pending', $values);
        $this->assertContains('confirmed', $values);
        $this->assertContains('waiting_list', $values);
        $this->assertContains('cancelled', $values);
        $this->assertContains('rejected', $values);
    }

    public function test_color_returns_appropriate_colors(): void
    {
        $this->assertEquals('warning', RegistrationState::Pending->color());
        $this->assertEquals('success', RegistrationState::Confirmed->color());
        $this->assertEquals('info', RegistrationState::WaitingList->color());
        $this->assertEquals('gray', RegistrationState::Cancelled->color());
        $this->assertEquals('danger', RegistrationState::Rejected->color());
    }

    public function test_only_pending_and_waiting_registrations_can_be_confirmed(): void
    {
        $this->assertSame(
            [RegistrationState::Pending, RegistrationState::WaitingList],
            array_values(array_filter(RegistrationState::cases(), fn (RegistrationState $state): bool => $state->canBeConfirmed())),
        );
    }

    public function test_cancelled_and_rejected_registrations_cannot_be_rejected(): void
    {
        $this->assertSame(
            [RegistrationState::Pending, RegistrationState::Confirmed, RegistrationState::WaitingList],
            array_values(array_filter(RegistrationState::cases(), fn (RegistrationState $state): bool => $state->canBeRejected())),
        );
    }

    public function test_only_pending_and_confirmed_registrations_can_move_to_the_waiting_list(): void
    {
        $this->assertSame(
            [RegistrationState::Pending, RegistrationState::Confirmed],
            array_values(array_filter(RegistrationState::cases(), fn (RegistrationState $state): bool => $state->canMoveToWaitingList())),
        );
    }
}
