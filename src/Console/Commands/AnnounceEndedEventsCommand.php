<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Console\Commands;

use Illuminate\Console\Command;
use Modules\EventRegistrations\Infrastructure\Surveys\EndedEventAnnouncer;

/**
 * Tells the surveys module (when installed) which events with registrations have ended, once
 * per event. Without it nothing listens and nothing happens.
 */
final class AnnounceEndedEventsCommand extends Command
{
    protected $signature = 'event-registrations:announce-ended-events';

    protected $description = 'Announce events that have ended to the surveys module';

    public function handle(EndedEventAnnouncer $announcer): int
    {
        $announced = $announcer->announce();

        $this->info("Announced {$announced} ended event(s).");

        return self::SUCCESS;
    }
}
