<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Unit\Services;

use Modules\EventRegistrations\Domain\Enums\ConfirmationSource;
use Modules\EventRegistrations\Infrastructure\Services\RegistrationNotificationService;
use Tests\TestCase;

final class RegistrationNotificationServiceTest extends TestCase
{
    private const string SETTINGS = 'modules.settings.event-registrations';

    public function test_manual_confirmation_is_never_announced_by_another_email(): void
    {
        $this->assertFalse(
            (new RegistrationNotificationService)->confirmationAlreadyAnnounced(ConfirmationSource::Manual),
        );
    }

    public function test_registration_email_announces_auto_confirmation_when_enabled(): void
    {
        config()->set(self::SETTINGS.'.send_registration_email', true);

        $this->assertTrue(
            (new RegistrationNotificationService)->confirmationAlreadyAnnounced(ConfirmationSource::Registration),
        );
    }

    public function test_auto_confirmation_is_not_announced_when_registration_email_disabled(): void
    {
        config()->set(self::SETTINGS.'.send_registration_email', false);

        $this->assertFalse(
            (new RegistrationNotificationService)->confirmationAlreadyAnnounced(ConfirmationSource::Registration),
        );
    }

    public function test_promotion_email_announces_promotion_confirmation_when_enabled(): void
    {
        config()->set(self::SETTINGS.'.send_promotion_email', true);

        $this->assertTrue(
            (new RegistrationNotificationService)->confirmationAlreadyAnnounced(ConfirmationSource::WaitingListPromotion),
        );
    }

    public function test_promotion_confirmation_is_not_announced_when_promotion_email_disabled(): void
    {
        config()->set(self::SETTINGS.'.send_promotion_email', false);

        $this->assertFalse(
            (new RegistrationNotificationService)->confirmationAlreadyAnnounced(ConfirmationSource::WaitingListPromotion),
        );
    }
}
