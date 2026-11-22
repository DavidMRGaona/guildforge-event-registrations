<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Infrastructure\Profiles;

use App\Application\Exceptions\NothingToDrawException;
use App\Application\Profiles\Contracts\ProfileSectionInterface;
use App\Application\Profiles\DTOs\ProfileOwnerDTO;
use App\Application\Services\TimezoneServiceInterface;
use App\Domain\Profiles\ProfileRegion;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * «Últimos eventos asistidos» (`event-registrations.past`): the latest events that ended with the
 * owner confirmed in them, the ones «Eventos asistidos» counts. A section of its own, apart from
 * «Próximos eventos», so the owner shows or hides each one alone. It lists them only for the people
 * who may see the owner's registrations (RegistrationsVisibleOnProfile), like the other section and
 * the stat.
 */
final readonly class PastEventsProfileSection implements ProfileSectionInterface
{
    public const string KEY = 'event-registrations.past';

    /** How many events the card lists: a profile is drawn on every visit, the latest are enough */
    public const int LIMIT = 5;

    public function __construct(
        private RegistrationsVisibleOnProfile $visibility,
        private ProfileEvents $events,
        private TimezoneServiceInterface $timezone,
    ) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return __('event-registrations::messages.public_profile.past.label');
    }

    public function description(): string
    {
        return __('event-registrations::messages.public_profile.past.description');
    }

    public function component(): string
    {
        return 'components/PastEventsSection.vue';
    }

    public function region(): ProfileRegion
    {
        return ProfileRegion::Aside;
    }

    /**
     * Right after «Próximos eventos».
     */
    public function order(): int
    {
        return 21;
    }

    public function enabledByDefault(): bool
    {
        return true;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    /**
     * @return array{events: list<array{title: string, url: string, startsAt: string|null, location: string|null}>}
     */
    public function props(ProfileOwnerDTO $owner, ?Authenticatable $viewer): array
    {
        if (! $this->visibility->allows($viewer, $owner->id)) {
            throw NothingToDrawException::make();
        }

        $events = $this->events->lastAttended($owner->id, self::LIMIT);

        if ($events->isEmpty()) {
            throw NothingToDrawException::make();
        }

        return ['events' => array_values($events->map(fn (EventModel $event): array => [
            'title' => $event->title,
            'url' => route('events.show', $event->slug, false),
            'startsAt' => $this->timezone->iso($event->start_date),
            'location' => $event->location,
        ])->all())];
    }
}
