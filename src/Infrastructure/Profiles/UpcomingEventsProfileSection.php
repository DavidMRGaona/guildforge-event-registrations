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
 * «Próximos eventos» (`event-registrations.upcoming`): the events the owner is confirmed in that have
 * not ended yet. It lists them only for the people who may see the owner's registrations
 * (RegistrationsVisibleOnProfile): the owner, and whoever could open the event's registrations tab in
 * the panel, so a registration that is not public on the module's pages is not public on the profile.
 */
final readonly class UpcomingEventsProfileSection implements ProfileSectionInterface
{
    public const string KEY = 'event-registrations.upcoming';

    /** How many events the card lists: the next ones are enough for a profile */
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
        return __('event-registrations::messages.public_profile.upcoming.label');
    }

    public function description(): string
    {
        return __('event-registrations::messages.public_profile.upcoming.description');
    }

    public function component(): string
    {
        return 'components/UpcomingEventsSection.vue';
    }

    public function region(): ProfileRegion
    {
        return ProfileRegion::Aside;
    }

    public function order(): int
    {
        return 20;
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

        $events = $this->events->upcoming($owner->id, self::LIMIT);

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
