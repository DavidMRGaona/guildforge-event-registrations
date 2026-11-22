<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Infrastructure\Profiles;

use App\Application\Services\TimezoneServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Domain\Services\Clock;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationModel;

/**
 * The published events a person is confirmed in, split at the moment the event ends: before it the
 * event is upcoming (one in progress is still to be attended), after it the person attended it.
 *
 * The module records no check-in, so confirmed in an event that has ended is the closest to
 * "attended" its data can tell. A pending, waiting-list, rejected or cancelled registration never
 * counts. This class knows nothing about who is looking: callers ask the profile's visibility rule
 * first.
 */
final readonly class ProfileEvents
{
    public function __construct(
        private TimezoneServiceInterface $timezone,
        private Clock $clock,
    ) {}

    /**
     * Soonest first; one query.
     *
     * @return Collection<int, EventModel>
     */
    public function upcoming(string $ownerId, int $limit): Collection
    {
        return $this->confirmedIn($ownerId)
            ->where('end_date', '>=', $this->now())
            ->orderedByStart()
            ->limit($limit)
            ->get(['id', 'title', 'slug', 'start_date', 'location']);
    }

    /**
     * The latest ones attended: the ones that ended most recently first, so a long event that began
     * earlier but ended later comes before a short one held in the middle of it. Ties go like
     * orderedByStart(): the latest created first, then by id. One query.
     *
     * @return Collection<int, EventModel>
     */
    public function lastAttended(string $ownerId, int $limit): Collection
    {
        return $this->attended($ownerId)
            ->orderByDesc('end_date')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'title', 'slug', 'start_date', 'location']);
    }

    /**
     * Events, not registrations: one query.
     */
    public function attendedCount(string $ownerId): int
    {
        return $this->attended($ownerId)->count();
    }

    /**
     * Ended before now: one ending right now is still upcoming, like in upcoming().
     *
     * @return Builder<EventModel>
     */
    private function attended(string $ownerId): Builder
    {
        return $this->confirmedIn($ownerId)->where('end_date', '<', $this->now());
    }

    /**
     * @return Builder<EventModel>
     */
    private function confirmedIn(string $ownerId): Builder
    {
        return EventModel::query()
            ->where('is_published', true)
            ->whereIn('id', EventRegistrationModel::query()
                ->select('event_id')
                ->where('user_id', $ownerId)
                ->where('state', RegistrationState::Confirmed->value));
    }

    /**
     * Query bounds are bound as given, so the instant goes in the storage zone.
     */
    private function now(): \DateTimeImmutable
    {
        return $this->timezone->toStorage($this->clock->now());
    }
}
