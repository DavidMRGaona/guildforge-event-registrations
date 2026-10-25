<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Notifications;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class EventReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly EventModel $event,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        /** @var UserModel $notifiable */
        $date = $this->event->start_date->format('d/m/Y H:i');

        return (new MailMessage)
            ->subject(__('event-registrations::messages.emails.reminder_subject', [
                'event' => $this->event->title,
                'date' => $date,
            ]))
            ->greeting(__('event-registrations::messages.emails.greeting', ['name' => $notifiable->name]))
            ->line(__('event-registrations::messages.emails.reminder_body', [
                'event' => $this->event->title,
                'date' => $date,
            ]))
            ->when($this->event->location, fn (MailMessage $mail): MailMessage => $mail->line('**'.__('event-registrations::messages.emails.location').':** '.$this->event->location))
            ->line(__('event-registrations::messages.emails.reminder_cancel'))
            ->action(__('event-registrations::messages.emails.view_event'), url('/eventos/'.$this->event->slug));
    }
}
