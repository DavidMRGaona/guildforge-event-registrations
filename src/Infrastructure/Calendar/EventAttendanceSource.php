<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Infrastructure\Calendar;

use App\Application\Calendar\Contracts\CalendarAttendanceSourceInterface;
use App\Application\Calendar\DTOs\CalendarEntryReferenceDTO;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use DateTimeImmutable;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationModel;

/**
 * Tells the personal calendar which core events a person is confirmed in. Pending, waiting list,
 * rejected and cancelled registrations are left out; the core drops events the public calendar
 * does not show, so publication is not checked here.
 */
final readonly class EventAttendanceSource implements CalendarAttendanceSourceInterface
{
    /** The core's calendar source type for events. */
    public const string EVENT_SOURCE_TYPE = 'event';

    /**
     * @return array<CalendarEntryReferenceDTO>
     */
    public function attendedEntries(string $userId, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        /** @var list<string> $eventIds */
        $eventIds = EventRegistrationModel::query()
            ->where('user_id', $userId)
            ->where('state', RegistrationState::Confirmed->value)
            ->whereIn('event_id', EventModel::query()
                ->select('id')
                ->where('start_date', '<', $to)
                ->where('end_date', '>', $from))
            ->distinct()
            ->pluck('event_id')
            ->all();

        return array_map(
            static fn (string $eventId): CalendarEntryReferenceDTO => new CalendarEntryReferenceDTO(self::EVENT_SOURCE_TYPE, $eventId),
            $eventIds,
        );
    }
}
