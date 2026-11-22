<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Infrastructure\Activity;

use App\Application\Activity\Contracts\ActivitySourceInterface;
use App\Application\Activity\DTOs\ActivityFactDTO;
use App\Application\Activity\DTOs\ActivityHistoryPageDTO;
use App\Application\Activity\DTOs\ActivitySubjectDTO;
use App\Application\Activity\DTOs\ActivitySubjectFactsDTO;
use App\Domain\Activity\ActivityKind;
use App\Domain\Activity\ActivityRole;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use DateTimeImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * «Eventos asistidos» for the activity contract (core#774): every published event that has ended,
 * with each confirmed registration of an account as an `attendee` fact at the event's end. There
 * is no check-in, so this is what the profile's `event-registrations.attended` stat counts, from
 * the same definition (AttendedEvents).
 *
 * Only what any signed-in member can know: published events and confirmed registrations. The answer
 * is the same for everybody and never reads the signed-in user, the session or the request, since
 * a queued job runs it. Registrations always belong to an account, so there are no guests to leave
 * out; anonymized accounts are dropped by the core's reader.
 */
final readonly class EventActivitySource implements ActivitySourceInterface
{
    public const string TYPE = 'event-registrations.event';

    private const string CURSOR_PREFIX = 'er1|';

    public function __construct(
        private AttendedEvents $attended,
    ) {}

    public function type(): string
    {
        return self::TYPE;
    }

    public function kind(): ActivityKind
    {
        return ActivityKind::Event;
    }

    public function label(): string
    {
        return __('event-registrations::messages.activity.event_label');
    }

    public function facts(string $subjectId): array
    {
        if (! Str::isUuid($subjectId)) {
            return [];
        }

        $event = $this->attended->endedWithConfirmed()->whereKey($subjectId)->first(['id', 'end_date']);

        if (! $event instanceof EventModel) {
            return [];
        }

        return $this->factsByEvent(new Collection([$event]))[$event->id] ?? [];
    }

    public function history(?string $cursor, int $limit): ActivityHistoryPageDTO
    {
        if ($limit < 1) {
            throw new InvalidArgumentException('The limit of a history page must be at least 1.');
        }

        $after = $cursor === null ? null : $this->decode($cursor);

        $events = $this->attended->endedWithConfirmed()
            ->when($after !== null, static fn ($query) => $query->where('id', '>', $after))
            ->orderBy('id')
            ->limit($limit + 1)
            ->get(['id', 'end_date']);

        $page = $events->take($limit);
        $last = $page->last();
        $facts = $this->factsByEvent($page);

        return new ActivityHistoryPageDTO(
            subjects: array_values($page->map(static fn (EventModel $event): ActivitySubjectFactsDTO => new ActivitySubjectFactsDTO($event->id, $facts[$event->id] ?? []))->all()),
            nextCursor: $events->count() > $limit && $last instanceof EventModel ? $this->encode($last->id) : null,
        );
    }

    /**
     * Published events are public pages, so the viewer makes no difference: guests see them too.
     * An event that has not ended yet is described as well, since the title is not a fact.
     */
    public function describe(array $subjectIds, ?Authenticatable $viewer): array
    {
        $ids = array_values(array_filter(
            array_map(strtolower(...), $subjectIds),
            static fn (string $id): bool => Str::isUuid($id),
        ));

        if ($ids === []) {
            return [];
        }

        $described = [];

        foreach ($this->attended->published()->whereIn('id', $ids)->get(['id', 'title', 'slug']) as $event) {
            $described[$event->id] = new ActivitySubjectDTO($event->id, $event->title, route('events.show', $event->slug, false));
        }

        return $described;
    }

    /**
     * One query for every registration of the events given.
     *
     * @param  Collection<int, EventModel>  $events
     * @return array<string, list<ActivityFactDTO>>
     */
    private function factsByEvent(Collection $events): array
    {
        if ($events->isEmpty()) {
            return [];
        }

        /** @var array<string, DateTimeImmutable> $endsAt */
        $endsAt = [];
        foreach ($events as $event) {
            $endsAt[$event->id] = DateTimeImmutable::createFromInterface($event->end_date);
        }

        $facts = [];

        foreach ($this->attended->confirmed()->whereIn('event_id', array_keys($endsAt))->orderBy('created_at')->orderBy('id')->get(['event_id', 'user_id']) as $registration) {
            $eventId = (string) $registration->event_id;

            if (! isset($endsAt[$eventId])) {
                continue;
            }

            $facts[$eventId][] = new ActivityFactDTO(
                userId: (string) $registration->user_id,
                role: ActivityRole::Attendee,
                occurredAt: $endsAt[$eventId],
            );
        }

        return $facts;
    }

    private function encode(string $eventId): string
    {
        return rtrim(strtr(base64_encode(self::CURSOR_PREFIX.$eventId), '+/', '-_'), '=');
    }

    private function decode(string $cursor): string
    {
        $decoded = preg_match('/^[A-Za-z0-9_-]+$/D', $cursor) === 1
            ? base64_decode(strtr($cursor, '-_', '+/'), true)
            : false;

        if (! is_string($decoded) || ! str_starts_with($decoded, self::CURSOR_PREFIX)) {
            throw new InvalidArgumentException('The history cursor cannot be read.');
        }

        $id = strtolower(substr($decoded, strlen(self::CURSOR_PREFIX)));

        if (! Str::isUuid($id)) {
            throw new InvalidArgumentException('The history cursor cannot be read.');
        }

        return $id;
    }
}
