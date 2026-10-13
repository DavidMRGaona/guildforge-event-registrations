<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Application\Services;

interface MembershipCheckerInterface
{
    /**
     * Whether the user may register to members-only events.
     */
    public function isMember(string $userId): bool;
}
