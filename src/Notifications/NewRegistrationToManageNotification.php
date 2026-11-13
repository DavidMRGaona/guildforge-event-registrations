<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Notifications;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Mail\Contracts\HasMailCategory;
use App\Application\Mail\Contracts\HasMailSubject;
use App\Application\Mail\DTOs\MailSubjectDTO;
use App\Application\SiteNotifications\Contracts\HasSiteNotification;
use App\Application\SiteNotifications\DTOs\SiteNotificationDTO;
use App\Application\SiteNotifications\DTOs\SiteNotificationGroupDTO;
use App\Filament\Resources\EventResource;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\EventRegistrationsServiceProvider;
use Modules\EventRegistrations\Filament\RelationManagers\RegistrationsRelationManager;

/**
 * Tells whoever manages registrations, in the bell only, that someone signed up for an event
 * (event-registrations#114). The mail to the address saved in the event is a separate notification
 * ({@see AdminNewRegistrationNotification}) and does not depend on this one.
 */
final class NewRegistrationToManageNotification extends Notification implements HasMailCategory, HasMailSubject, HasSiteNotification, ShouldQueue
{
    use Queueable;

    /**
     * Managing is changing registrations, and the notice links to the registrations tab of the
     * event in the panel: whoever gets it must be able to open that tab (panel, event edit page,
     * registrations list). Shared by the recipients, the check on delivery and the category audience.
     */
    public const array MANAGE_PERMISSIONS = [
        'admin.access',
        'events.update',
        'event-registrations:registrations.view_any',
        'event-registrations:registrations.update',
    ];

    private const string GROUP_KEY_PREFIX = 'event-registrations.signups:';

    public function __construct(
        private readonly string $eventId,
        private readonly string $registrationId,
        private readonly string $registrantId,
        private readonly string $registrantName,
        private readonly RegistrationState $state,
        private readonly ?int $position,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return [HasSiteNotification::CHANNEL];
    }

    public function mailCategory(): string
    {
        return EventRegistrationsServiceProvider::MAIL_MANAGEMENT;
    }

    /**
     * The event, as for the notices to attendees: muting it stops this one too, and deleting the
     * event forgets it.
     */
    public function mailSubject(): ?MailSubjectDTO
    {
        $event = $this->event();

        if (! $event instanceof EventModel) {
            return null;
        }

        return new MailSubjectDTO(
            EventReminderNotification::SUBJECT_TYPE,
            $this->eventId,
            $event->title,
            'event-registrations::messages.mail.subject_event',
        );
    }

    /**
     * Checked when it is delivered, not when it was queued: a role may have been taken away or
     * paused, or the event deleted, while the notice waited.
     */
    public function toSiteNotification(object $notifiable): ?SiteNotificationDTO
    {
        if (! app(AuthorizationServiceInterface::class)->canAll($notifiable, self::MANAGE_PERMISSIONS)) {
            return null;
        }

        $event = $this->event();

        if (! $event instanceof EventModel) {
            return null;
        }

        $eventTitle = $event->title;
        $registrantName = $this->registrantName;

        return new SiteNotificationDTO(
            title: __('event-registrations::messages.notices.manager_registered', [
                'user' => $registrantName,
                'event' => $eventTitle,
            ]),
            // The panel finds events by slug (EventModel::getRouteKeyName())
            url: EventResource::getUrl(
                'edit',
                ['record' => $event, 'relation' => RegistrationsRelationManager::RELATION_KEY],
                isAbsolute: false,
            ),
            body: $this->stateLine(),
            icon: 'user-plus',
            groupKey: self::GROUP_KEY_PREFIX.$this->eventId,
            actorId: $this->registrantId,
            groupedTitle: static fn (SiteNotificationGroupDTO $group): string => trans_choice(
                'event-registrations::messages.notices.manager_registered_grouped',
                $group->otherActors,
                ['user' => $registrantName, 'count' => $group->otherActors, 'event' => $eventTitle],
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event_id' => $this->eventId,
            'registration_id' => $this->registrationId,
            'user_id' => $this->registrantId,
            'state' => $this->state->value,
            'type' => 'manager_new_registration',
        ];
    }

    private function stateLine(): string
    {
        if ($this->state === RegistrationState::WaitingList && $this->position !== null) {
            return __('event-registrations::messages.notices.manager_state_position', [
                'state' => $this->state->label(),
                'position' => $this->position,
            ]);
        }

        return __('event-registrations::messages.notices.manager_state', ['state' => $this->state->label()]);
    }

    private function event(): ?EventModel
    {
        return EventModel::query()->find($this->eventId);
    }
}
