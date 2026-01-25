<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Notifications;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\EventRegistrations\Domain\Entities\EventRegistration;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;

final class AdminNewRegistrationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly EventModel $event,
        private readonly EventRegistration $registration,
        private readonly UserModel $user,
    ) {}

    /**
     * @return array<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('event-registrations::messages.emails.admin_new_registration_subject', [
                'event' => $this->event->title,
            ]))
            ->greeting(__('event-registrations::messages.emails.admin_greeting'))
            ->line(__('event-registrations::messages.emails.admin_new_registration_body', [
                'user' => $this->user->name,
                'email' => $this->user->email,
                'event' => $this->event->title,
            ]));

        // Add state information
        $state = $this->registration->state();
        $stateLabel = $state->label();

        $mail->line(__('event-registrations::messages.emails.admin_registration_state', [
            'state' => $stateLabel,
        ]));

        if ($state === RegistrationState::WaitingList) {
            $mail->line(__('event-registrations::messages.emails.admin_waiting_list_position', [
                'position' => $this->registration->position() ?? '-',
            ]));
        }

        if ($state === RegistrationState::Pending) {
            $mail->line(__('event-registrations::messages.emails.admin_requires_confirmation'));
        }

        return $mail
            ->action(__('event-registrations::messages.emails.admin_view_registrations'), url('/admin/events/'.$this->event->id.'/edit?activeRelationManager=1'))
            ->salutation(__('event-registrations::messages.emails.salutation'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event_id' => $this->event->id,
            'event_title' => $this->event->title,
            'registration_id' => $this->registration->id()->value,
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'type' => 'admin_new_registration',
        ];
    }
}
