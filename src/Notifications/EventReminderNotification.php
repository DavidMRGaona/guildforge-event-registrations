<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Notifications;

use App\Application\Mail\Contracts\HasMailCategory;
use App\Application\Mail\Contracts\HasMailSubject;
use App\Application\Services\TimezoneServiceInterface;
use App\Application\SiteNotifications\Contracts\HasSiteNotification;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\EventRegistrations\EventRegistrationsServiceProvider;
use Modules\EventRegistrations\Notifications\Concerns\DeliversSiteNotice;

final class EventReminderNotification extends Notification implements HasMailCategory, HasMailSubject, HasSiteNotification, ShouldQueue
{
    use DeliversSiteNotice;
    use Queueable;

    public function mailCategory(): string
    {
        return EventRegistrationsServiceProvider::MAIL_REMINDERS;
    }

    public function __construct(
        private readonly EventModel $event,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', HasSiteNotification::CHANNEL];
    }

    public function toMail(object $notifiable): MailMessage
    {
        /** @var UserModel $notifiable */
        $date = app(TimezoneServiceInterface::class)->format($this->event->start_date);

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

    private function siteNoticeTitle(): string
    {
        return __('event-registrations::messages.notices.reminder', ['event' => $this->event->title]);
    }

    private function siteNoticeIcon(): string
    {
        return 'bell';
    }
}
