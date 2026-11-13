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
use Modules\EventRegistrations\Domain\Entities\EventRegistration;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\EventRegistrationsServiceProvider;
use Modules\EventRegistrations\Notifications\Concerns\AddsOrganiserMessage;
use Modules\EventRegistrations\Notifications\Concerns\DeliversSiteNotice;

final class UserRegisteredNotification extends Notification implements HasMailCategory, HasMailSubject, HasSiteNotification, ShouldQueue
{
    use AddsOrganiserMessage;
    use DeliversSiteNotice;
    use Queueable;

    public function __construct(
        private readonly EventModel $event,
        private readonly EventRegistration $registration,
        private readonly ?string $organiserMessage = null,
    ) {}

    public function mailCategory(): string
    {
        return EventRegistrationsServiceProvider::MAIL_REGISTRATIONS;
    }

    /**
     * @return array<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', HasSiteNotification::CHANNEL];
    }

    public function toMail(object $notifiable): MailMessage
    {
        /** @var UserModel $notifiable */
        $message = (new MailMessage)
            ->subject(__($this->subjectKey(), [
                'event' => $this->event->title,
            ]))
            ->greeting(__('event-registrations::messages.emails.greeting', [
                'name' => $notifiable->name,
            ]))
            ->line(__($this->bodyKey(), [
                'event' => $this->event->title,
            ]));

        // Add state-specific information
        if ($this->registration->state() === RegistrationState::WaitingList) {
            $message->line(__('event-registrations::messages.emails.your_position', [
                'position' => $this->registration->position(),
            ]));
            $message->line(__('event-registrations::messages.emails.waiting_list_info'));
        } elseif ($this->registration->state() === RegistrationState::Pending) {
            $message->line(__('event-registrations::messages.emails.registered_pending'));
        } elseif ($this->registration->state() === RegistrationState::Confirmed) {
            $this->addOrganiserMessage($message, $this->organiserMessage);
        }

        return $message
            ->line(__('event-registrations::messages.emails.event_details'))
            ->line('**'.__('event-registrations::messages.emails.date').':** '.app(TimezoneServiceInterface::class)->format($this->event->start_date))
            ->when($this->event->location, fn (MailMessage $mail): MailMessage => $mail->line('**'.__('event-registrations::messages.emails.location').':** '.$this->event->location))
            ->action(__('event-registrations::messages.emails.view_event'), url('/eventos/'.$this->event->slug));
    }

    /**
     * Like the subject, the opening line matches the resulting state.
     */
    private function bodyKey(): string
    {
        return match ($this->registration->state()) {
            RegistrationState::Confirmed => 'event-registrations::messages.emails.registered_confirmed_body',
            RegistrationState::WaitingList => 'event-registrations::messages.emails.registered_waiting_list_body',
            default => 'event-registrations::messages.emails.registered_body',
        };
    }

    /**
     * The registration email is the only one sent on sign-up, so its subject
     * must reflect the resulting state instead of always saying "received".
     */
    private function subjectKey(): string
    {
        return match ($this->registration->state()) {
            RegistrationState::Confirmed => 'event-registrations::messages.emails.confirmed_subject',
            RegistrationState::WaitingList => 'event-registrations::messages.emails.waiting_list_subject',
            default => 'event-registrations::messages.emails.registered_subject',
        };
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
            'state' => $this->registration->state()->value,
            'position' => $this->registration->position(),
            'type' => 'user_registered',
        ];
    }

    private function siteNoticeTitle(): string
    {
        return __(match ($this->registration->state()) {
            RegistrationState::Confirmed => 'event-registrations::messages.notices.registered_confirmed',
            RegistrationState::WaitingList => 'event-registrations::messages.notices.registered_waiting_list',
            default => 'event-registrations::messages.notices.registered',
        }, ['event' => $this->event->title]);
    }

    private function siteNoticeIcon(): string
    {
        return 'calendar-check';
    }
}
