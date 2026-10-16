<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Application\Services;

interface PublishedEventCheckerInterface
{
    /**
     * Whether the core event exists and is published; false for an unknown id.
     */
    public function isPublished(string $eventId): bool;
}
