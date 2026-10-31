<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Integration\Surveys;

use App\Domain\Surveys\Events\SurveySubjectEnded;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Domain\Services\Clock;
use Modules\EventRegistrations\Infrastructure\Surveys\EndedEventAnnouncer;
use Modules\EventRegistrations\Tests\Support\BuildsSurveyableEvents;
use Modules\EventRegistrations\Tests\Support\FixedClock;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Events have no "ended" moment of their own: the announcer tells the surveys module (through the
 * core's event) once per event, after its end has passed.
 */
final class EndedEventAnnouncerTest extends ModuleTestCase
{
    use BuildsSurveyableEvents;

    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useUtcStorage();
        Event::fake([SurveySubjectEnded::class]);
        $this->at('2030-01-05T12:00:00+00:00');
    }

    public function test_an_ended_event_with_confirmed_registrations_is_announced_once(): void
    {
        $event = $this->surveyableEvent('2030-01-05T08:00:00+00:00', '2030-01-05T11:30:00+00:00');
        $this->registrant($event);

        $this->assertSame(1, $this->announcer()->announce());
        $this->assertSame(0, $this->announcer()->announce());

        Event::assertDispatchedTimes(SurveySubjectEnded::class, 1);
        Event::assertDispatched(SurveySubjectEnded::class, fn (SurveySubjectEnded $ended): bool => $ended->sourceType === 'event'
            && $ended->subjectId === $event->id
            && $ended->endedAt == new DateTimeImmutable('2030-01-05T11:30:00+00:00'));
    }

    public function test_an_event_ending_right_now_is_announced(): void
    {
        $this->registrant($this->surveyableEvent('2030-01-05T08:00:00+00:00', '2030-01-05T12:00:00+00:00'));

        $this->assertSame(1, $this->announcer()->announce());
    }

    public function test_an_event_still_running_is_not_announced(): void
    {
        $this->registrant($this->surveyableEvent('2030-01-05T08:00:00+00:00', '2030-01-05T12:00:01+00:00'));

        $this->assertSame(0, $this->announcer()->announce());
        Event::assertNotDispatched(SurveySubjectEnded::class);
    }

    public function test_events_without_confirmed_registrations_unpublished_or_without_registrations_are_not_announced(): void
    {
        $empty = $this->surveyableEvent('2030-01-05T08:00:00+00:00', '2030-01-05T11:00:00+00:00');
        $this->registrant($empty, RegistrationState::WaitingList);
        $this->registrant($empty, RegistrationState::Cancelled);
        $this->registrant($this->surveyableEvent('2030-01-05T08:00:00+00:00', '2030-01-05T11:00:00+00:00', published: false));
        $this->registrant($this->surveyableEvent('2030-01-05T08:00:00+00:00', '2030-01-05T11:00:00+00:00', ['registration_enabled' => false]));

        $this->assertSame(0, $this->announcer()->announce());
        Event::assertNotDispatched(SurveySubjectEnded::class);
    }

    /**
     * 25 October 2026, Madrid: 02:00-02:59 happens twice. At 00:45Z (02:45 CEST, the first pass)
     * an event ending at 01:30Z (02:30 CET, the second pass) is still running, although its wall
     * clock time is earlier; one ending at 00:30Z (02:30 CEST) has ended.
     */
    public function test_an_event_ending_in_the_repeated_hour_is_judged_by_instant(): void
    {
        $this->at('2026-10-25T00:45:00+00:00');
        $ended = $this->surveyableEvent('2026-10-24T22:00:00+00:00', '2026-10-25T00:30:00+00:00');
        $running = $this->surveyableEvent('2026-10-24T22:00:00+00:00', '2026-10-25T01:30:00+00:00');
        $this->registrant($ended);
        $this->registrant($running);

        $this->assertSame(1, $this->announcer()->announce());

        Event::assertDispatched(SurveySubjectEnded::class, fn (SurveySubjectEnded $event): bool => $event->subjectId === $ended->id);
        Event::assertNotDispatched(SurveySubjectEnded::class, fn (SurveySubjectEnded $event): bool => $event->subjectId === $running->id);
    }

    public function test_an_overdue_event_is_announced_after_a_scheduler_gap(): void
    {
        $event = $this->surveyableEvent('2029-12-30T08:00:00+00:00', '2029-12-30T11:00:00+00:00');
        $this->registrant($event);

        $this->assertSame(1, $this->announcer()->announce());
        Event::assertDispatched(SurveySubjectEnded::class, fn (SurveySubjectEnded $ended): bool => $ended->subjectId === $event->id);
    }

    public function test_an_event_republished_after_its_end_is_announced_then(): void
    {
        $event = $this->surveyableEvent('2030-01-05T08:00:00+00:00', '2030-01-05T11:00:00+00:00', published: false);
        $this->registrant($event);
        $this->assertSame(0, $this->announcer()->announce());

        $event->update(['is_published' => true]);

        $this->assertSame(1, $this->announcer()->announce());
    }

    public function test_a_claimed_event_is_not_announced_again(): void
    {
        $event = $this->surveyableEvent('2030-01-05T08:00:00+00:00', '2030-01-05T11:00:00+00:00');
        $this->registrant($event);
        // Another run claimed it first
        DB::table(EndedEventAnnouncer::TABLE)->insert(['event_id' => $event->id, 'announced_at' => '2030-01-05 11:15:00']);

        $this->assertSame(0, $this->announcer()->announce());
        Event::assertNotDispatched(SurveySubjectEnded::class);
    }

    public function test_the_announcement_waits_for_the_commit(): void
    {
        $this->registrant($this->surveyableEvent('2030-01-05T08:00:00+00:00', '2030-01-05T11:00:00+00:00'));

        DB::transaction(function (): void {
            $this->announcer()->announce();

            Event::assertNotDispatched(SurveySubjectEnded::class);
        });

        Event::assertDispatchedTimes(SurveySubjectEnded::class, 1);
    }

    private function at(string $instant): void
    {
        $this->app->instance(Clock::class, FixedClock::at($instant));
    }

    private function announcer(): EndedEventAnnouncer
    {
        return $this->app->make(EndedEventAnnouncer::class);
    }
}
