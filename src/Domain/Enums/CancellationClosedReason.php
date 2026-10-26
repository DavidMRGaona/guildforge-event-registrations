<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Domain\Enums;

/**
 * Why a registration can no longer be cancelled.
 */
enum CancellationClosedReason: string
{
    /** The event has ended; it wins over the other reasons. */
    case EventEnded = 'event_ended';

    /** The event has started (or its start is unknown); it wins over a passed deadline. */
    case EventStarted = 'event_started';

    /** The cancellation deadline has passed before the event starts. */
    case DeadlinePassed = 'deadline_passed';
}
