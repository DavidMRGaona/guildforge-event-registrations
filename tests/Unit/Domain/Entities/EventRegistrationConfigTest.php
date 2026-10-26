<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Unit\Domain\Entities;

use DateTimeImmutable;
use Modules\EventRegistrations\Domain\Entities\EventRegistrationConfig;
use Modules\EventRegistrations\Domain\Enums\CancellationClosedReason;
use PHPUnit\Framework\TestCase;

final class EventRegistrationConfigTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createConfig(array $overrides = []): EventRegistrationConfig
    {
        return new EventRegistrationConfig(
            eventId: $overrides['eventId'] ?? 'event-123',
            registrationEnabled: $overrides['registrationEnabled'] ?? true,
            maxParticipants: $overrides['maxParticipants'] ?? null,
            waitingListEnabled: $overrides['waitingListEnabled'] ?? true,
            maxWaitingList: $overrides['maxWaitingList'] ?? null,
            registrationOpensAt: $overrides['registrationOpensAt'] ?? null,
            registrationClosesAt: $overrides['registrationClosesAt'] ?? null,
            cancellationDeadline: $overrides['cancellationDeadline'] ?? null,
            requiresConfirmation: $overrides['requiresConfirmation'] ?? false,
            // A future start unless the test says otherwise (null included)
            eventStartsAt: array_key_exists('eventStartsAt', $overrides)
                ? $overrides['eventStartsAt']
                : new DateTimeImmutable('+1 week'),
            eventEndsAt: $overrides['eventEndsAt'] ?? null,
        );
    }

    public function test_it_creates_config_with_required_data(): void
    {
        $config = new EventRegistrationConfig(eventId: 'event-123');

        $this->assertEquals('event-123', $config->eventId());
        $this->assertTrue($config->isRegistrationEnabled());
        $this->assertNull($config->maxParticipants());
        $this->assertTrue($config->isWaitingListEnabled());
    }

    public function test_is_open_returns_true_when_enabled_and_no_dates(): void
    {
        $config = $this->createConfig();

        $this->assertTrue($config->isOpen());
    }

    public function test_is_open_returns_false_when_disabled(): void
    {
        $config = $this->createConfig(['registrationEnabled' => false]);

        $this->assertFalse($config->isOpen());
    }

    public function test_is_open_returns_false_before_opens_at(): void
    {
        $config = $this->createConfig([
            'registrationOpensAt' => new DateTimeImmutable('+1 day'),
        ]);

        $this->assertFalse($config->isOpen());
    }

    public function test_is_open_returns_false_after_closes_at(): void
    {
        $config = $this->createConfig([
            'registrationClosesAt' => new DateTimeImmutable('-1 day'),
        ]);

        $this->assertFalse($config->isOpen());
    }

    public function test_is_open_returns_true_within_date_range(): void
    {
        $config = $this->createConfig([
            'registrationOpensAt' => new DateTimeImmutable('-1 day'),
            'registrationClosesAt' => new DateTimeImmutable('+1 day'),
        ]);

        $this->assertTrue($config->isOpen());
    }

    public function test_has_participant_limit_when_max_is_set(): void
    {
        $configWithLimit = $this->createConfig(['maxParticipants' => 10]);
        $configWithoutLimit = $this->createConfig(['maxParticipants' => null]);

        $this->assertTrue($configWithLimit->hasParticipantLimit());
        $this->assertFalse($configWithoutLimit->hasParticipantLimit());
    }

    public function test_has_waiting_list_limit_when_max_is_set(): void
    {
        $configWithLimit = $this->createConfig(['maxWaitingList' => 5]);
        $configWithoutLimit = $this->createConfig(['maxWaitingList' => null]);

        $this->assertTrue($configWithLimit->hasWaitingListLimit());
        $this->assertFalse($configWithoutLimit->hasWaitingListLimit());
    }

    public function test_can_cancel_now_returns_true_when_no_deadline(): void
    {
        $config = $this->createConfig();

        $this->assertTrue($config->canCancelNow());
    }

    public function test_can_cancel_now_returns_true_before_deadline(): void
    {
        $config = $this->createConfig([
            'cancellationDeadline' => new DateTimeImmutable('+1 day'),
        ]);

        $this->assertTrue($config->canCancelNow());
    }

    public function test_can_cancel_now_returns_false_after_deadline(): void
    {
        $config = $this->createConfig([
            'cancellationDeadline' => new DateTimeImmutable('-1 day'),
        ]);

        $this->assertFalse($config->canCancelNow());
    }

    public function test_it_sets_registration_enabled(): void
    {
        $config = $this->createConfig();

        $config->setRegistrationEnabled(false);

        $this->assertFalse($config->isRegistrationEnabled());
    }

    public function test_it_sets_max_participants(): void
    {
        $config = $this->createConfig();

        $config->setMaxParticipants(20);

        $this->assertEquals(20, $config->maxParticipants());
    }

    public function test_it_sets_waiting_list_enabled(): void
    {
        $config = $this->createConfig();

        $config->setWaitingListEnabled(false);

        $this->assertFalse($config->isWaitingListEnabled());
    }

    public function test_it_sets_requires_confirmation(): void
    {
        $config = $this->createConfig();

        $config->setRequiresConfirmation(true);

        $this->assertTrue($config->requiresConfirmation());
    }

    public function test_it_sets_members_only(): void
    {
        $config = $this->createConfig();

        $config->setMembersOnly(true);

        $this->assertTrue($config->isMembersOnly());
    }

    public function test_it_sets_custom_fields(): void
    {
        $config = $this->createConfig();
        $fields = [
            ['name' => 'phone', 'label' => 'Phone', 'type' => 'text', 'required' => true],
        ];

        $config->setCustomFields($fields);

        $this->assertEquals($fields, $config->customFields());
    }

    public function test_the_event_has_not_started_just_before_its_start(): void
    {
        $config = $this->createConfig(['eventStartsAt' => new DateTimeImmutable('2030-04-12 10:00:00')]);

        $this->assertFalse($config->hasEventStarted(new DateTimeImmutable('2030-04-12 09:59:59')));
    }

    public function test_the_event_has_started_at_its_exact_start(): void
    {
        $config = $this->createConfig(['eventStartsAt' => new DateTimeImmutable('2030-04-12 10:00:00')]);

        $this->assertTrue($config->hasEventStarted(new DateTimeImmutable('2030-04-12 10:00:00')));
    }

    public function test_the_event_has_started_after_its_start(): void
    {
        $config = $this->createConfig(['eventStartsAt' => new DateTimeImmutable('2030-04-12 10:00:00')]);

        $this->assertTrue($config->hasEventStarted(new DateTimeImmutable('2030-04-12 10:00:01')));
    }

    public function test_an_event_whose_start_is_unknown_counts_as_started(): void
    {
        $config = new EventRegistrationConfig(eventId: 'event-123');

        $this->assertNull($config->eventStartsAt());
        $this->assertTrue($config->hasEventStarted(new DateTimeImmutable('2000-01-01 00:00:00')));
    }

    public function test_it_exposes_the_event_start(): void
    {
        $startsAt = new DateTimeImmutable('2030-04-12 10:00:00');

        $this->assertSame($startsAt, $this->createConfig(['eventStartsAt' => $startsAt])->eventStartsAt());
    }

    public function test_registration_is_open_just_before_the_event_starts(): void
    {
        $config = $this->createConfig(['eventStartsAt' => new DateTimeImmutable('2030-04-12 10:00:00')]);

        $this->assertTrue($config->isOpen(new DateTimeImmutable('2030-04-12 09:59:59')));
    }

    public function test_registration_closes_at_the_exact_start(): void
    {
        $config = $this->createConfig(['eventStartsAt' => new DateTimeImmutable('2030-04-12 10:00:00')]);

        $this->assertFalse($config->isOpen(new DateTimeImmutable('2030-04-12 10:00:00')));
    }

    public function test_registration_stays_closed_after_the_start(): void
    {
        $config = $this->createConfig(['eventStartsAt' => new DateTimeImmutable('2030-04-12 10:00:00')]);

        $this->assertFalse($config->isOpen(new DateTimeImmutable('2030-04-12 10:00:01')));
    }

    public function test_a_close_date_after_the_start_does_not_keep_registration_open(): void
    {
        $config = $this->createConfig([
            'eventStartsAt' => new DateTimeImmutable('2030-04-12 10:00:00'),
            'registrationClosesAt' => new DateTimeImmutable('2030-04-12 12:00:00'),
        ]);

        $this->assertTrue($config->isOpen(new DateTimeImmutable('2030-04-12 09:00:00')));
        $this->assertFalse($config->isOpen(new DateTimeImmutable('2030-04-12 11:00:00')));
    }

    public function test_registration_is_closed_when_the_event_start_is_unknown(): void
    {
        $config = $this->createConfig(['eventStartsAt' => null]);

        $this->assertFalse($config->isOpen(new DateTimeImmutable('2000-01-01 00:00:00')));
    }

    public function test_cancellation_is_allowed_just_before_the_start_without_a_deadline(): void
    {
        $config = $this->createConfig(['eventStartsAt' => new DateTimeImmutable('2030-04-12 10:00:00')]);

        $this->assertTrue($config->canCancelNow(new DateTimeImmutable('2030-04-12 09:59:59')));
    }

    public function test_cancellation_closes_at_the_exact_start_without_a_deadline(): void
    {
        $config = $this->createConfig(['eventStartsAt' => new DateTimeImmutable('2030-04-12 10:00:00')]);

        $this->assertFalse($config->canCancelNow(new DateTimeImmutable('2030-04-12 10:00:00')));
    }

    public function test_a_deadline_after_the_start_does_not_keep_cancellation_open(): void
    {
        $config = $this->createConfig([
            'eventStartsAt' => new DateTimeImmutable('2030-04-12 10:00:00'),
            'cancellationDeadline' => new DateTimeImmutable('2030-04-12 12:00:00'),
        ]);

        $this->assertFalse($config->canCancelNow(new DateTimeImmutable('2030-04-12 11:00:00')));
    }

    public function test_an_earlier_deadline_still_closes_cancellation_before_the_start(): void
    {
        $config = $this->createConfig([
            'eventStartsAt' => new DateTimeImmutable('2030-04-12 10:00:00'),
            'cancellationDeadline' => new DateTimeImmutable('2030-04-12 08:00:00'),
        ]);

        $this->assertTrue($config->canCancelNow(new DateTimeImmutable('2030-04-12 08:00:00')));
        $this->assertFalse($config->canCancelNow(new DateTimeImmutable('2030-04-12 09:00:00')));
    }

    public function test_cancellation_is_refused_when_the_event_start_is_unknown(): void
    {
        $config = $this->createConfig(['eventStartsAt' => null]);

        $this->assertFalse($config->canCancelNow(new DateTimeImmutable('2000-01-01 00:00:00')));
    }

    public function test_an_open_cancellation_has_no_closed_reason(): void
    {
        $config = $this->createConfig([
            'eventStartsAt' => new DateTimeImmutable('2030-04-12 10:00:00'),
            'cancellationDeadline' => new DateTimeImmutable('2030-04-12 08:00:00'),
        ]);

        $this->assertNull($config->cancellationClosedReason(new DateTimeImmutable('2030-04-12 08:00:00')));
    }

    public function test_a_passed_deadline_is_the_closed_reason_before_the_start(): void
    {
        $config = $this->createConfig([
            'eventStartsAt' => new DateTimeImmutable('2030-04-12 10:00:00'),
            'cancellationDeadline' => new DateTimeImmutable('2030-04-12 08:00:00'),
        ]);

        $this->assertSame(
            CancellationClosedReason::DeadlinePassed,
            $config->cancellationClosedReason(new DateTimeImmutable('2030-04-12 09:00:00')),
        );
    }

    public function test_the_start_is_the_closed_reason_once_the_event_has_started_even_after_the_deadline(): void
    {
        $config = $this->createConfig([
            'eventStartsAt' => new DateTimeImmutable('2030-04-12 10:00:00'),
            'cancellationDeadline' => new DateTimeImmutable('2030-04-12 08:00:00'),
        ]);

        $this->assertSame(
            CancellationClosedReason::EventStarted,
            $config->cancellationClosedReason(new DateTimeImmutable('2030-04-12 10:00:00')),
        );
    }

    public function test_an_unknown_start_is_closed_as_started(): void
    {
        $config = $this->createConfig(['eventStartsAt' => null]);

        $this->assertSame(
            CancellationClosedReason::EventStarted,
            $config->cancellationClosedReason(new DateTimeImmutable('2000-01-01 00:00:00')),
        );
    }

    public function test_the_event_has_not_ended_just_before_its_end(): void
    {
        $config = $this->createConfig([
            'eventStartsAt' => new DateTimeImmutable('2030-04-12 10:00:00'),
            'eventEndsAt' => new DateTimeImmutable('2030-04-12 14:00:00'),
        ]);

        $this->assertFalse($config->hasEventEnded(new DateTimeImmutable('2030-04-12 13:59:59')));
        $this->assertTrue($config->hasEventEnded(new DateTimeImmutable('2030-04-12 14:00:00')));
    }

    public function test_an_event_whose_end_is_unknown_never_counts_as_ended(): void
    {
        $config = $this->createConfig(['eventStartsAt' => new DateTimeImmutable('2030-04-12 10:00:00')]);

        $this->assertFalse($config->hasEventEnded(new DateTimeImmutable('2031-01-01 00:00:00')));
    }

    public function test_the_end_is_the_closed_reason_once_the_event_has_ended_even_after_the_deadline(): void
    {
        $config = $this->createConfig([
            'eventStartsAt' => new DateTimeImmutable('2030-04-12 10:00:00'),
            'eventEndsAt' => new DateTimeImmutable('2030-04-12 14:00:00'),
            'cancellationDeadline' => new DateTimeImmutable('2030-04-12 08:00:00'),
        ]);

        $this->assertSame(
            CancellationClosedReason::EventStarted,
            $config->cancellationClosedReason(new DateTimeImmutable('2030-04-12 13:59:59')),
        );
        $this->assertSame(
            CancellationClosedReason::EventEnded,
            $config->cancellationClosedReason(new DateTimeImmutable('2030-04-12 14:00:00')),
        );
        $this->assertFalse($config->canCancelNow(new DateTimeImmutable('2030-04-12 14:00:00')));
    }
}
