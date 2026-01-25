<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Notifications;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\EventRegistrations\Domain\Entities\EventRegistration;

final class WaitingListPromotedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly EventModel $event,
        private readonly EventRegistration $registration,
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
            ->subject(__('event-registrations::messages.emails.promoted_subject', [
                'event' => $this->event->title,
            ]))
            ->greeting(__('event-registrations::messages.emails.greeting', [
                'name' => $notifiable->name,
            ]))
            ->line(__('event-registrations::messages.emails.promoted_body', [
                'event' => $this->event->title,
            ]))
            ->line(__('event-registrations::messages.emails.promoted_info'))
            ->line(__('event-registrations::messages.emails.event_details'))
            ->line('**'.__('event-registrations::messages.emails.date').':** '.$this->event->start_date->format('d/m/Y H:i'))
            ->when($this->event->location, function (MailMessage $message): MailMessage {
                return $message->line('**'.__('event-registrations::messages.emails.location').':** '.$this->event->location);
            })
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
            'type' => 'waiting_list_promoted',
        ];
    }
}
