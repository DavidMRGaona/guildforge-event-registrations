<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Console\Commands;

use Illuminate\Console\Command;
use Modules\EventRegistrations\Infrastructure\Activity\EndedEventActivityAnnouncer;
use Modules\EventRegistrations\Infrastructure\Surveys\EndedEventAnnouncer;

/**
 * Tells the surveys module (when installed) which events with registrations have ended, once
 * per event, and publishes the activity of every ended event with confirmed registrations for the
 * activity contract's consumers (the achievements module), once per event. Without a listener
 * nothing happens.
 */
final class AnnounceEndedEventsCommand extends Command
{
    protected $signature = 'event-registrations:announce-ended-events';

    protected $description = 'Announce events that have ended to the surveys module and publish their activity';

    public function handle(EndedEventAnnouncer $announcer, EndedEventActivityAnnouncer $activity): int
    {
        $announced = $announcer->announce();

        $this->info("Announced {$announced} ended event(s).");

        $published = $activity->announce();

        $this->info("Published the activity of {$published} ended event(s).");

        return self::SUCCESS;
    }
}
