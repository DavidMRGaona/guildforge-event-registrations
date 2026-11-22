<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Infrastructure\Activity;

use App\Application\Activity\Services\ActivityPublisherInterface;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Events have no "ended" moment of their own, and time alone changes nothing for the activity
 * contract, so this publishes (through the core's ActivityPublisherInterface) every published event
 * whose end has passed and that has someone confirmed in it, once. It is independent of the
 * surveys' EndedEventAnnouncer: an event needs no survey, nor registrations switched on, to count.
 *
 * Each event is claimed in a table, with the end it was published for, before it is published, so
 * it goes out once even when two runs overlap. An event is picked up again only when its end is no
 * longer the claimed one (it moved and has ended again), which also settles a save that lands
 * between reading an event and claiming it: the claim keeps the end that was read, so the new end
 * never matches it. A change to an event that has already ended is published by
 * AnnouncesActivityChanges as it happens.
 *
 * The claim is written before the publication, the same pattern as the surveys' announcer: if
 * publishing fails after it, the event is not retried, and only a recalculation by the consumer
 * recovers it.
 *
 * The first run after the table is created also publishes the events that ended before it: with
 * no consumer nothing listens, and a consumer that is installed reads them as a recalculation.
 */
final readonly class EndedEventActivityAnnouncer
{
    public const string TABLE = 'event_registrations_activity_ends';

    private const int CHUNK = 200;

    public function __construct(
        private AttendedEvents $attended,
        private ActivityPublisherInterface $activity,
    ) {}

    /**
     * @return int How many events were published
     */
    public function announce(): int
    {
        $now = $this->attended->now();
        $published = 0;

        $this->attended->endedWithConfirmed()
            ->whereNotExists(static fn ($claim) => $claim->from(self::TABLE)
                ->whereColumn(self::TABLE.'.event_id', 'events.id')
                ->whereColumn(self::TABLE.'.ends_at', 'events.end_date'))
            ->chunkById(self::CHUNK, function (Collection $events) use ($now, &$published): void {
                /** @var EventModel $event */
                foreach ($events as $event) {
                    if (! $this->claim($event, $now)) {
                        continue;
                    }

                    $this->activity->recorded(EventActivitySource::TYPE, $event->id);
                    $published++;
                }
            });

        return $published;
    }

    /**
     * Claims the event for the end that was read, as stored (the claim and the event compare the
     * same text). Of two runs that read the same event, one wins.
     */
    private function claim(EventModel $event, DateTimeImmutable $now): bool
    {
        $endsAt = (string) $event->getRawOriginal('end_date');
        $announcedAt = $now->format('Y-m-d H:i:s');

        try {
            if (DB::table(self::TABLE)->insertOrIgnore(['event_id' => $event->id, 'ends_at' => $endsAt, 'announced_at' => $announcedAt]) === 1) {
                return true;
            }
        } catch (QueryException $exception) {
            // The event was deleted after it was read: the foreign key refuses the claim (PostgreSQL's
            // ON CONFLICT only covers unique violations), and the deletion was published already.
            // Any other failure is not this one's to swallow.
            if (EventModel::query()->whereKey($event->id)->exists()) {
                throw $exception;
            }

            return false;
        }

        return DB::table(self::TABLE)
            ->where('event_id', $event->id)
            ->where('ends_at', '!=', $endsAt)
            ->update(['ends_at' => $endsAt, 'announced_at' => $announcedAt]) === 1;
    }
}
