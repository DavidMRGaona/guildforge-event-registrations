<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Infrastructure\Activity;

use App\Application\Activity\Services\ActivityPublisherInterface;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Events have no "ended" moment of their own, and time alone changes nothing for the activity
 * contract, so this publishes (through the core's ActivityPublisherInterface) every published event
 * whose end has passed and that has someone confirmed in it, once. It is independent of the
 * surveys' EndedEventAnnouncer: an event needs no survey, nor registrations switched on, to count.
 *
 * Each event is claimed in a table before it is published, so it goes out once even when two runs
 * overlap. A change to an event that has already ended is published by AnnouncesActivityChanges
 * as it happens; an end date moved into the future releases the claim, so the event is published
 * again when it really ends.
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
            ->whereNotIn('id', DB::table(self::TABLE)->select('event_id'))
            ->chunkById(self::CHUNK, function (Collection $events) use ($now, &$published): void {
                /** @var EventModel $event */
                foreach ($events as $event) {
                    $claimed = DB::table(self::TABLE)->insertOrIgnore([
                        'event_id' => $event->id,
                        'announced_at' => $now->format('Y-m-d H:i:s'),
                    ]) === 1;

                    if (! $claimed) {
                        continue;
                    }

                    $this->activity->recorded(EventActivitySource::TYPE, $event->id);
                    $published++;
                }
            });

        return $published;
    }

    /**
     * An event whose end moved away must be announced again when it ends.
     */
    public function release(string $eventId): void
    {
        DB::table(self::TABLE)->where('event_id', $eventId)->delete();
    }
}
