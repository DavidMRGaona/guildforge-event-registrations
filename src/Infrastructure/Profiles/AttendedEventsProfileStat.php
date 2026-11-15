<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Infrastructure\Profiles;

use App\Application\Profiles\Contracts\ProfileStatInterface;
use App\Application\Profiles\DTOs\ProfileOwnerDTO;
use App\Application\Profiles\DTOs\ProfileStatDTO;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * «Eventos asistidos» (`event-registrations.attended`): how many published events ended with the owner
 * confirmed in them. Counted only for the people who may see the owner's registrations; for anybody
 * else it is `null`, never a 0 that would claim the owner went to nothing.
 */
final readonly class AttendedEventsProfileStat implements ProfileStatInterface
{
    public const string KEY = 'event-registrations.attended';

    public function __construct(
        private RegistrationsVisibleOnProfile $visibility,
        private ProfileEvents $events,
    ) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return __('event-registrations::messages.public_profile.attended.label');
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

    public function stat(ProfileOwnerDTO $owner, ?Authenticatable $viewer): ?ProfileStatDTO
    {
        if (! $this->visibility->allows($viewer, $owner->id)) {
            return null;
        }

        $count = $this->events->attendedCount($owner->id);

        // Nothing attended yet is nothing to show, like an empty list
        return $count === 0 ? null : new ProfileStatDTO($count);
    }
}
