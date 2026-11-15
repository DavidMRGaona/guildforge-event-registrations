<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Infrastructure\Profiles;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\EventRegistrations\Policies\RegistrationListAccess;

/**
 * Whether the person looking at a public profile may see that owner's registrations.
 *
 * The module has no public list of attendees. A person sees their own registrations in
 * «Mis inscripciones», and the people registered in an event are listed only in the event's
 * «Inscripciones» tab of the panel. So the owner of the profile sees theirs, and anybody else sees
 * them only if they could open that tab: the profile asks RegistrationListAccess, the rule the tab
 * itself uses, and never one of its own. That rule is one answer per person, not per event.
 *
 * Bound scoped: the section and the stat of one profile ask the same question, answered once per
 * request or job. A failure is never remembered, so it is asked again, and fails again, closed.
 */
final class RegistrationsVisibleOnProfile
{
    /** @var array<string, bool> */
    private array $answers = [];

    public function __construct(private readonly RegistrationListAccess $access) {}

    public function allows(?Authenticatable $viewer, string $ownerId): bool
    {
        // The rules are written for accounts: a guest or any other guard's user sees nothing
        if (! $viewer instanceof UserModel) {
            return false;
        }

        if ($viewer->id === $ownerId) {
            return true;
        }

        return $this->answers[$viewer->id] ??= $this->access->mayOpenEventTabs($viewer);
    }
}
