<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Notifications;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\EventRegistrations\Domain\Entities\EventRegistration;

final class WaitingListAddedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly EventModel $event,
        private readonly EventRegistration $registration,
        private readonly int $position,
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
        return (new MailMessage)
            ->subject(__('event-registrations::messages.emails.waiting_list_subject', [
                'event' => $this->event->title,
            ]))
            ->greeting(__('event-registrations::messages.emails.greeting', [
                'name' => $notifiable->name,
            ]))
            ->line(__('event-registrations::messages.emails.waiting_list_body', [
                'event' => $this->event->title,
            ]))
            ->line(__('event-registrations::messages.emails.your_position', [
                'position' => $this->position,
            ]))
            ->line(__('event-registrations::messages.emails.waiting_list_info'))
            ->action(__('event-registrations::messages.emails.view_event'), url('/eventos/'.$this->event->slug))
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
            'position' => $this->position,
            'type' => 'waiting_list_added',
        ];
    }
}
