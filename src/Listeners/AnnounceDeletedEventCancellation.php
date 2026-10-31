<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Listeners;

use App\Application\Services\TimezoneServiceInterface;
use App\Domain\Surveys\Events\SurveySubjectCancelled;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Modules\EventRegistrations\Domain\Services\Clock;
use Modules\EventRegistrations\Infrastructure\Surveys\EventSurveySubjectSource;

/**
 * An event deleted before it ended will never end: tells the surveys module (through the core's
 * SurveySubjectCancelled), so a survey prepared for its end is dropped instead of waiting forever.
 * Events are core records, so this listens to the model rather than to an event of the module.
 */
final readonly class AnnounceDeletedEventCancellation
{
    public function __construct(
        private Clock $clock,
        private TimezoneServiceInterface $timezone,
    ) {}

    public function handle(EventModel $event): void
    {
        $now = $this->timezone->toStorage($this->clock->now());

        if (DateTimeImmutable::createFromInterface($event->end_date) <= $now) {
            return;
        }

        $eventId = $event->id;
        DB::afterCommit(static fn () => event(new SurveySubjectCancelled(EventSurveySubjectSource::TYPE, $eventId, $now)));
    }
}
