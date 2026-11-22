<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Infrastructure\Activity;

use App\Application\Services\TimezoneServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Domain\Services\Clock;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationModel;

/**
 * What "attended" means, once: a confirmed registration in a published event that has ended. The
 * module records no check-in, so this is the closest its data can tell. The profile's
 * `event-registrations.attended` stat and the activity source both ask it, so the two can never
 * disagree.
 *
 * It knows nothing about who is looking: callers add their own rule (the profile's visibility, the
 * activity contract's "any signed-in member"). An event is ended once its end has passed, an
 * instant compared in the storage zone, so the hour repeated in October never ends one early.
 */
final readonly class AttendedEvents
{
    public function __construct(
        private TimezoneServiceInterface $timezone,
        private Clock $clock,
    ) {}

    /**
     * Published events, over or not: the base of every question.
     *
     * @return Builder<EventModel>
     */
    public function published(): Builder
    {
        return EventModel::query()->where('is_published', true);
    }

    /**
     * Published events whose end has passed.
     *
     * @return Builder<EventModel>
     */
    public function ended(): Builder
    {
        return $this->published()->where('end_date', '<', $this->now());
    }

    /**
     * Ended events with someone confirmed in them: the subjects that have facts.
     *
     * @return Builder<EventModel>
     */
    public function endedWithConfirmed(): Builder
    {
        return $this->ended()->whereIn('id', $this->confirmed()->select('event_id'));
    }

    /**
     * Published events one person is confirmed in, over or not.
     *
     * @return Builder<EventModel>
     */
    public function confirmedBy(string $userId): Builder
    {
        return $this->published()->whereIn('id', $this->confirmed()->where('user_id', $userId)->select('event_id'));
    }

    /**
     * Ended events one person attended.
     *
     * @return Builder<EventModel>
     */
    public function attendedBy(string $userId): Builder
    {
        return $this->confirmedBy($userId)->where('end_date', '<', $this->now());
    }

    /**
     * @return Builder<EventRegistrationModel>
     */
    public function confirmed(): Builder
    {
        return EventRegistrationModel::query()->where('state', RegistrationState::Confirmed->value);
    }

    /**
     * Whether an event with this publication state and end counts as ended right now.
     */
    public function hasEnded(bool $published, ?DateTimeInterface $endsAt): bool
    {
        return $published && $endsAt instanceof DateTimeInterface && DateTimeImmutable::createFromInterface($endsAt) < $this->now();
    }

    /**
     * Query bounds are bound as given, so the instant goes in the storage zone.
     */
    public function now(): DateTimeImmutable
    {
        return $this->timezone->toStorage($this->clock->now());
    }
}
