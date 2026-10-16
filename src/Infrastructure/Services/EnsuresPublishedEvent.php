<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Infrastructure\Services;

use Modules\EventRegistrations\Domain\Exceptions\EventNotFoundException;

/**
 * The single place that turns "unknown or unpublished event" into an exception.
 * The using class must declare a `PublishedEventCheckerInterface $publishedEvents` property.
 */
trait EnsuresPublishedEvent
{
    /**
     * User paths only: staff actions work on any event (D4).
     */
    private function ensurePublished(string $eventId): void
    {
        if (! $this->publishedEvents->isPublished($eventId)) {
            throw EventNotFoundException::notPublished($eventId);
        }
    }
}
