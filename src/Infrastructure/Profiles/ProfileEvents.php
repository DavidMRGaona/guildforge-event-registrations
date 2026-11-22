<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Infrastructure\Profiles;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Illuminate\Database\Eloquent\Collection;
use Modules\EventRegistrations\Infrastructure\Activity\AttendedEvents;

/**
 * The published events a person is confirmed in, split at the moment the event ends: before it the
 * event is upcoming (one in progress is still to be attended), after it the person attended it.
 *
 * The module records no check-in, so confirmed in an event that has ended is the closest to
 * "attended" its data can tell (see AttendedEvents, which the activity source shares). A pending,
 * waiting-list, rejected or cancelled registration never counts. This class knows nothing about who
 * is looking: callers ask the profile's visibility rule first.
 */
final readonly class ProfileEvents
{
    public function __construct(
        private AttendedEvents $attended,
    ) {}

    /**
     * Soonest first; one query.
     *
     * @return Collection<int, EventModel>
     */
    public function upcoming(string $ownerId, int $limit): Collection
    {
        return $this->attended->confirmedBy($ownerId)
            ->where('end_date', '>=', $this->attended->now())
            ->orderedByStart()
            ->limit($limit)
            ->get(['id', 'title', 'slug', 'start_date', 'location']);
    }

    /**
     * Events, not registrations: one query.
     */
    public function attendedCount(string $ownerId): int
    {
        return $this->attended->attendedBy($ownerId)->count();
    }
}
