<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Infrastructure\Activity;

use App\Application\Activity\Services\ActivityPublisherInterface;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use DateTimeZone;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationModel;

/**
 * Publishes, through the core's ActivityPublisherInterface, every change to the facts of an event
 * that has already ended (EndedEventActivityAnnouncer does the moment it ends): a registration
 * confirmed, cancelled, moved or removed after the end; the event unpublished, published again,
 * deleted, or with its end date moved (the announcer publishes it again when it ends at the new
 * date); an account removed for good. Events are core records and
 * registrations are saved by the panel, the repository and plain models, so the provider wires this
 * to the models rather than to any one path.
 *
 * Something that does not count is not published: an event still to come has nothing to say until
 * it ends, and an unpublished one has no facts. Publishing for a save that changed nothing would be
 * harmless, but it is noise a consumer would pay for.
 */
final readonly class AnnouncesActivityChanges
{
    public function __construct(
        private AttendedEvents $attended,
        private ActivityPublisherInterface $activity,
    ) {}

    /**
     * Inside `saved` the model still holds the values it had before the save (a new one has none),
     * and `isDirty()` tells what this save changed.
     */
    public function registrationSaved(EventRegistrationModel $registration): void
    {
        $was = RegistrationState::tryFrom((string) $registration->getRawOriginal('state')) === RegistrationState::Confirmed;
        $is = $registration->state === RegistrationState::Confirmed;
        $moved = $registration->isDirty(['event_id', 'user_id']);

        // Still (or still not) confirmed in the same place: nothing about attendance changed
        if ($was === $is && ! $moved) {
            return;
        }

        if ($is) {
            $this->publishIfCounted((string) $registration->event_id);
        }

        if ($was) {
            $this->publishIfCounted((string) $registration->getRawOriginal('event_id'));
        }
    }

    public function registrationDeleted(EventRegistrationModel $registration): void
    {
        if ($registration->state === RegistrationState::Confirmed) {
            $this->publishIfCounted((string) $registration->event_id);
        }
    }

    public function eventSaved(EventModel $event): void
    {
        // A new event has no registrations to report; a save that changed neither has nothing to say
        if ($event->getRawOriginal('end_date') === null || ! $event->isDirty(['is_published', 'end_date'])) {
            return;
        }

        $counted = $this->attended->hasEnded((bool) $event->getRawOriginal('is_published'), $this->originalEnd($event))
            || $this->attended->hasEnded((bool) $event->is_published, $event->end_date);

        if ($counted) {
            $this->activity->recorded(EventActivitySource::TYPE, $event->id);
        }
    }

    public function eventDeleted(EventModel $event): void
    {
        if ($this->attended->hasEnded((bool) $event->is_published, $event->end_date)) {
            $this->activity->recorded(EventActivitySource::TYPE, $event->id);
        }
    }

    /**
     * The events an account attended, asked before it is removed (`forceDeleting`): the
     * registrations go with it (a foreign key, no model event) and nothing could be asked after.
     *
     * @return list<string>
     */
    public function attendedEventIds(UserModel $user): array
    {
        return array_values(array_map(
            static fn (mixed $id): string => (string) $id,
            $this->attended->attendedBy((string) $user->getKey())->pluck('id')->all(),
        ));
    }

    /**
     * Once the account is gone (`forceDeleted`): a forced deletion opens no transaction, so
     * publishing earlier would let the consumer read the registrations still there. Deactivating
     * an account keeps its registrations, and its facts with them.
     *
     * @param  list<string>  $eventIds
     */
    public function accountRemoved(array $eventIds): void
    {
        foreach ($eventIds as $eventId) {
            $this->activity->recorded(EventActivitySource::TYPE, $eventId);
        }
    }

    /**
     * The end the event had before this save, read raw: an instant is stored in the storage zone,
     * which is PHP's default one.
     */
    private function originalEnd(EventModel $event): ?DateTimeImmutable
    {
        $raw = $event->getRawOriginal('end_date');

        return is_string($raw) && $raw !== '' ? new DateTimeImmutable($raw, new DateTimeZone(date_default_timezone_get())) : null;
    }

    private function publishIfCounted(string $eventId): void
    {
        if ($this->attended->ended()->whereKey($eventId)->exists()) {
            $this->activity->recorded(EventActivitySource::TYPE, $eventId);
        }
    }
}
