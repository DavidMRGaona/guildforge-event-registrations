<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Notifications\Concerns;

use App\Application\Mail\DTOs\MailSubjectDTO;
use App\Application\SiteNotifications\DTOs\SiteNotificationDTO;
use Modules\EventRegistrations\Application\Services\PublishedEventCheckerInterface;

/**
 * Bell notice (and mail subject) of a notification about one event. The using class holds the event
 * in `$event` and names the notice with {@see self::siteNoticeTitle()} and {@see self::siteNoticeIcon()}.
 */
trait DeliversSiteNotice
{
    public const string SUBJECT_TYPE = 'event-registrations.event';

    abstract private function siteNoticeTitle(): string;

    abstract private function siteNoticeIcon(): string;

    /**
     * Whoever mutes the optional notices about an event stops them here and in the mail.
     */
    public function mailSubject(): MailSubjectDTO
    {
        return new MailSubjectDTO(
            self::SUBJECT_TYPE,
            (string) $this->event->id,
            $this->event->title,
            'event-registrations::messages.mail.subject_event',
        );
    }

    /**
     * Checked when the notice is delivered, not when it was queued: the event may have been
     * unpublished or deleted while it waited.
     */
    public function toSiteNotification(object $notifiable): ?SiteNotificationDTO
    {
        if (! app(PublishedEventCheckerInterface::class)->isPublished((string) $this->event->id)) {
            return null;
        }

        return new SiteNotificationDTO(
            title: $this->siteNoticeTitle(),
            url: route('events.show', ['slug' => $this->event->slug], absolute: false),
            icon: $this->siteNoticeIcon(),
        );
    }
}
