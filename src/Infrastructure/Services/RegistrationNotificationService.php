<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Notification;
use Modules\EventRegistrations\Application\Services\RegistrationNotificationServiceInterface;
use Modules\EventRegistrations\Domain\Entities\EventRegistration;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationConfigModel;
use Modules\EventRegistrations\Notifications\AdminNewRegistrationNotification;
use Modules\EventRegistrations\Notifications\RegistrationCancelledNotification;
use Modules\EventRegistrations\Notifications\RegistrationConfirmedNotification;
use Modules\EventRegistrations\Notifications\RegistrationRejectedNotification;
use Modules\EventRegistrations\Notifications\UserRegisteredNotification;
use Modules\EventRegistrations\Notifications\WaitingListAddedNotification;
use Modules\EventRegistrations\Notifications\WaitingListPromotedNotification;

final readonly class RegistrationNotificationService implements RegistrationNotificationServiceInterface
{
    public function sendRegistrationEmail(EventRegistration $registration): void
    {
        if (! config('modules.settings.event-registrations.send_registration_email', true)) {
            return;
        }

        $user = UserModel::find($registration->userId());
        $event = EventModel::find($registration->eventId());

        if ($user === null || $event === null) {
            return;
        }

        if ($this->isEventPast($event)) {
            return;
        }

        $user->notify(new UserRegisteredNotification($event, $registration));
    }

    public function sendConfirmationEmail(EventRegistration $registration): void
    {
        if (! config('modules.settings.event-registrations.send_confirmation_email', true)) {
            return;
        }

        $user = UserModel::find($registration->userId());
        $event = EventModel::find($registration->eventId());

        if ($user === null || $event === null) {
            return;
        }

        if ($this->isEventPast($event)) {
            return;
        }

        $user->notify(new RegistrationConfirmedNotification($event, $registration));
    }

    public function sendWaitingListEmail(EventRegistration $registration, int $position): void
    {
        if (! config('modules.settings.event-registrations.send_waiting_list_email', true)) {
            return;
        }

        $user = UserModel::find($registration->userId());
        $event = EventModel::find($registration->eventId());

        if ($user === null || $event === null) {
            return;
        }

        if ($this->isEventPast($event)) {
            return;
        }

        $user->notify(new WaitingListAddedNotification($event, $registration, $position));
    }

    public function sendPromotionEmail(EventRegistration $registration): void
    {
        if (! config('modules.settings.event-registrations.send_promotion_email', true)) {
            return;
        }

        $user = UserModel::find($registration->userId());
        $event = EventModel::find($registration->eventId());

        if ($user === null || $event === null) {
            return;
        }

        if ($this->isEventPast($event)) {
            return;
        }

        $user->notify(new WaitingListPromotedNotification($event, $registration));
    }

    public function sendCancellationEmail(EventRegistration $registration): void
    {
        if (! config('modules.settings.event-registrations.send_cancellation_email', true)) {
            return;
        }

        $user = UserModel::find($registration->userId());
        $event = EventModel::find($registration->eventId());

        if ($user === null || $event === null) {
            return;
        }

        if ($this->isEventPast($event)) {
            return;
        }

        $user->notify(new RegistrationCancelledNotification($event, $registration));
    }

    public function sendRejectionEmail(EventRegistration $registration): void
    {
        if (! config('modules.settings.event-registrations.send_rejection_email', true)) {
            return;
        }

        $user = UserModel::find($registration->userId());
        $event = EventModel::find($registration->eventId());

        if ($user === null || $event === null) {
            return;
        }

        if ($this->isEventPast($event)) {
            return;
        }

        $user->notify(new RegistrationRejectedNotification($event, $registration));
    }

    public function sendAdminNotification(EventRegistration $registration): void
    {
        $event = EventModel::find($registration->eventId());

        if ($event === null) {
            return;
        }

        if ($this->isEventPast($event)) {
            return;
        }

        // Get the notification email from event config
        $config = EventRegistrationConfigModel::find($registration->eventId());

        if ($config === null || empty($config->notification_email)) {
            return;
        }

        $user = UserModel::find($registration->userId());

        if ($user === null) {
            return;
        }

        // Send notification to the configured email using on-demand notification
        Notification::route('mail', $config->notification_email)
            ->notify(new AdminNewRegistrationNotification($event, $registration, $user));
    }

    private function isEventPast(EventModel $event): bool
    {
        $endDate = $event->end_date ?? $event->start_date;

        return $endDate->isPast();
    }
}
