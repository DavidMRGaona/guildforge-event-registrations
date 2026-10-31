<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Integration\Surveys;

use App\Domain\Surveys\Events\SurveySubjectCancelled;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\EventRegistrations\Domain\Services\Clock;
use Modules\EventRegistrations\Tests\Support\BuildsSurveyableEvents;
use Modules\EventRegistrations\Tests\Support\FixedClock;
use Tests\Support\Modules\ModuleTestCase;

/**
 * An event deleted before it ended will never end: the surveys module is told, so a survey
 * prepared to go out on its end does not wait forever.
 */
final class EventDeletionCancelsSurveySubjectTest extends ModuleTestCase
{
    use BuildsSurveyableEvents;

    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useUtcStorage();
        Event::fake([SurveySubjectCancelled::class]);
        $this->app->instance(Clock::class, FixedClock::at('2030-01-05T12:00:00+00:00'));
    }

    public function test_deleting_an_event_that_has_not_ended_announces_the_cancellation(): void
    {
        $event = $this->surveyableEvent('2030-01-05T10:00:00+00:00', '2030-01-05T12:00:01+00:00');
        $id = $event->id;

        $event->delete();

        Event::assertDispatchedTimes(SurveySubjectCancelled::class, 1);
        Event::assertDispatched(SurveySubjectCancelled::class, fn (SurveySubjectCancelled $cancelled): bool => $cancelled->sourceType === 'event'
            && $cancelled->subjectId === $id
            && $cancelled->cancelledAt == new DateTimeImmutable('2030-01-05T12:00:00+00:00'));
    }

    public function test_deleting_an_ended_event_announces_nothing(): void
    {
        $this->surveyableEvent('2030-01-05T10:00:00+00:00', '2030-01-05T12:00:00+00:00')->delete();

        Event::assertNotDispatched(SurveySubjectCancelled::class);
    }

    public function test_the_cancellation_waits_for_the_commit(): void
    {
        $event = $this->surveyableEvent('2030-01-06T10:00:00+00:00', '2030-01-06T12:00:00+00:00');

        DB::transaction(function () use ($event): void {
            $event->delete();

            Event::assertNotDispatched(SurveySubjectCancelled::class);
        });

        Event::assertDispatchedTimes(SurveySubjectCancelled::class, 1);
    }
}
