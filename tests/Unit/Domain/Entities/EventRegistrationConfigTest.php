<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Unit\Domain\Entities;

use DateTimeImmutable;
use Modules\EventRegistrations\Domain\Entities\EventRegistrationConfig;
use PHPUnit\Framework\TestCase;

final class EventRegistrationConfigTest extends TestCase
{
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
}
