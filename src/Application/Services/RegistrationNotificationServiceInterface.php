<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Application\Services;

use Modules\EventRegistrations\Domain\Entities\EventRegistration;

interface RegistrationNotificationServiceInterface
{
    /**
     * Send email when user registers for an event.
     */
    public function sendRegistrationEmail(EventRegistration $registration): void;

    /**
     * Send confirmation email when registration is confirmed.
     */
    public function sendConfirmationEmail(EventRegistration $registration): void;

    /**
     * Send email when user is added to waiting list.
     */
    public function sendWaitingListEmail(EventRegistration $registration, int $position): void;

    /**
     * Send email when user is promoted from waiting list.
     */
    public function sendPromotionEmail(EventRegistration $registration): void;

    /**
     * Send email when registration is cancelled.
     */
    public function sendCancellationEmail(EventRegistration $registration): void;

    /**
     * Send email when registration is rejected.
     */
    public function sendRejectionEmail(EventRegistration $registration): void;

    /**
     * Send notification to event admin when someone registers.
     */
    public function sendAdminNotification(EventRegistration $registration): void;
}
