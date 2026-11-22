<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Integration\Activity;

use App\Application\Activity\Services\ActivityPublisherInterface;
use App\Domain\Activity\Events\ActivityRecorded;
use App\Domain\Surveys\Events\SurveySubjectEnded;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use DateTimeImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\PendingCommand;
use Modules\EventRegistrations\Console\Commands\AnnounceEndedEventsCommand;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Domain\Services\Clock;
use Modules\EventRegistrations\Infrastructure\Activity\EndedEventActivityAnnouncer;
use Modules\EventRegistrations\Tests\Support\BuildsProfileRegistrations;
use Modules\EventRegistrations\Tests\Support\BuildsSurveyableEvents;
use Modules\EventRegistrations\Tests\Support\FixedClock;
use Tests\Support\Modules\ModuleTestCase;

/**
 * An event has no "ended" moment of its own, so the activity of every published event that ended
 * with confirmed registrations is published once, whether or not it has a survey.
 */
final class EndedEventActivityAnnouncerTest extends ModuleTestCase
{
    use BuildsProfileRegistrations;
    use BuildsSurveyableEvents;

    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pinTheClock();
        $this->app->make(Kernel::class)->registerCommand($this->app->make(AnnounceEndedEventsCommand::class));
    }

    public function test_an_ended_event_with_confirmed_registrations_is_published_once_even_without_a_survey(): void
    {
        $event = $this->pastEvent();
        $this->register($event);
        $this->listen();

        $this->assertSame(1, $this->announcer()->announce());
        $this->assertSame(0, $this->announcer()->announce());

        Event::assertDispatchedTimes(ActivityRecorded::class, 1);
        Event::assertDispatched(ActivityRecorded::class, fn (ActivityRecorded $recorded): bool => $recorded->sourceType === 'event-registrations.event' && $recorded->subjectId === $event->id);
    }

    public function test_an_event_with_registrations_on_is_published_too(): void
    {
        $event = $this->surveyableEvent('2030-06-10 08:00:00 UTC', '2030-06-10 11:00:00 UTC');
        $this->registrant($event);
        $this->listen();

        $this->assertSame(1, $this->announcer()->announce());
        Event::assertDispatched(ActivityRecorded::class, fn (ActivityRecorded $recorded): bool => $recorded->subjectId === $event->id);
    }

    public function test_an_event_that_ends_right_now_waits_for_the_next_run(): void
    {
        // Facts count an event once its end has passed, the same as the profile's stat
        $this->register($this->eventAt('2030-06-15 08:00:00', self::NOW));
        $this->listen();

        $this->assertSame(0, $this->announcer()->announce());

        $this->app->instance(Clock::class, FixedClock::at('2030-06-15 12:00:01 UTC'));
        $this->assertSame(1, $this->announcer()->announce());
    }

    public function test_events_that_have_nothing_to_report_are_not_published_and_not_claimed(): void
    {
        $this->register($this->upcomingEvent());
        $this->pastEvent('Sin inscripciones');
        $waiting = $this->pastEvent('Lista de espera');
        $this->register($waiting, RegistrationState::WaitingList);
        $this->register($this->pastEvent('Cancelada'), RegistrationState::Cancelled);
        $draft = $this->pastEvent('Borrador');
        $this->register($draft);
        $draft->update(['is_published' => false]);
        $this->listen();

        $this->assertSame(0, $this->announcer()->announce());
        Event::assertNotDispatched(ActivityRecorded::class);
        $this->assertSame(0, DB::table(EndedEventActivityAnnouncer::TABLE)->count());
    }

    public function test_an_event_published_after_its_end_is_published_when_it_is_published(): void
    {
        $draft = $this->pastEvent('Borrador');
        $this->register($draft);
        $draft->update(['is_published' => false]);
        $this->assertSame(0, $this->announcer()->announce());

        $draft->update(['is_published' => true]);
        $this->listen();

        $this->assertSame(1, $this->announcer()->announce());
    }

    public function test_a_claimed_event_is_not_published_again(): void
    {
        $event = $this->pastEvent();
        $this->register($event);
        // Another run claimed it first
        DB::table(EndedEventActivityAnnouncer::TABLE)->insert(['event_id' => $event->id, 'ends_at' => $event->getRawOriginal('end_date'), 'announced_at' => '2030-06-05 16:15:00']);
        $this->listen();

        $this->assertSame(0, $this->announcer()->announce());
        Event::assertNotDispatched(ActivityRecorded::class);
    }

    public function test_the_claim_keeps_the_end_that_was_published(): void
    {
        $event = $this->pastEvent();
        $this->register($event);
        $this->listen();

        $this->announcer()->announce();

        $this->assertSame(
            $event->getRawOriginal('end_date'),
            DB::table(EndedEventActivityAnnouncer::TABLE)->where('event_id', $event->id)->value('ends_at'),
        );
    }

    public function test_an_event_whose_end_changed_after_it_was_claimed_is_published_again_when_it_ends(): void
    {
        $event = $this->pastEvent();
        $this->register($event);
        $this->assertSame(1, $this->announcer()->announce());

        $event->update(['end_date' => new DateTimeImmutable('2030-07-01 10:00:00 UTC')]);
        $this->assertSame(0, $this->announcer()->announce());

        $this->app->instance(Clock::class, FixedClock::at('2030-07-01 10:00:01 UTC'));
        $this->listen();

        $this->assertSame(1, $this->announcer()->announce());
        $this->assertSame(0, $this->announcer()->announce());
    }

    /**
     * The event is saved with a later end after the announcer read it and before it claimed it: the
     * claim keeps the end that was read, so the event is announced again when it really ends.
     */
    public function test_a_save_between_reading_and_claiming_does_not_lose_the_real_end(): void
    {
        $event = $this->pastEvent();
        $this->register($event);
        $newEnd = '2030-07-01 10:00:00';
        $moved = false;
        EventModel::retrieved(function (EventModel $read) use (&$moved, $event, $newEnd): void {
            if ($moved || $read->id !== $event->id) {
                return;
            }
            $moved = true;
            DB::table('events')->where('id', $event->id)->update(['end_date' => $newEnd]);
        });
        $this->listen();

        $this->assertSame(1, $this->announcer()->announce());
        $this->assertTrue($moved);

        $this->app->instance(Clock::class, FixedClock::at('2030-07-01 10:00:01 UTC'));
        $this->listen();

        $this->assertSame(1, $this->announcer()->announce());
        Event::assertDispatched(ActivityRecorded::class, fn (ActivityRecorded $recorded): bool => $recorded->subjectId === $event->id);
    }

    /**
     * Another run claimed the same end after this one read the event: the insert is ignored and the
     * conditional update finds the end already claimed, so nothing is published twice.
     */
    public function test_a_run_that_loses_the_claim_to_another_publishes_nothing(): void
    {
        $event = $this->pastEvent();
        $this->register($event);
        $claimed = false;
        EventModel::retrieved(function (EventModel $read) use (&$claimed, $event): void {
            if ($claimed || $read->id !== $event->id) {
                return;
            }
            $claimed = true;
            DB::table(EndedEventActivityAnnouncer::TABLE)->insert(['event_id' => $event->id, 'ends_at' => $read->getRawOriginal('end_date'), 'announced_at' => '2030-06-15 11:59:59']);
        });
        $this->listen();

        $this->assertSame(0, $this->announcer()->announce());
        $this->assertTrue($claimed);
        Event::assertNotDispatched(ActivityRecorded::class);
    }

    public function test_an_event_deleted_after_it_was_read_is_skipped_and_the_rest_go_on(): void
    {
        $doomed = $this->pastEvent('Se borra', 10);
        $kept = $this->pastEvent('Se queda', 11);
        $this->register($doomed);
        $this->register($kept);
        $deleted = false;
        EventModel::retrieved(function (EventModel $read) use (&$deleted, $doomed): void {
            if ($deleted || $read->id !== $doomed->id) {
                return;
            }
            $deleted = true;
            DB::table('events')->where('id', $doomed->id)->delete();
        });
        $this->listen();

        $this->assertSame(1, $this->announcer()->announce());
        $this->assertTrue($deleted);
        Event::assertDispatchedTimes(ActivityRecorded::class, 1);
        Event::assertDispatched(ActivityRecorded::class, fn (ActivityRecorded $recorded): bool => $recorded->subjectId === $kept->id);
        $this->assertSame(0, DB::table(EndedEventActivityAnnouncer::TABLE)->where('event_id', $doomed->id)->count());
    }

    public function test_an_event_whose_end_moves_away_and_comes_back_to_the_claimed_one_is_not_published_again(): void
    {
        $event = $this->pastEvent();
        $this->register($event);
        $claimedEnd = DateTimeImmutable::createFromInterface($event->end_date);
        $this->assertSame(1, $this->announcer()->announce());

        $event->update(['end_date' => new DateTimeImmutable('2030-07-01 10:00:00 UTC')]);
        $event->update(['end_date' => $claimedEnd]);
        $this->listen();

        $this->assertSame(0, $this->announcer()->announce());
        Event::assertNotDispatched(ActivityRecorded::class);
    }

    public function test_an_overdue_event_is_published_after_a_scheduler_gap(): void
    {
        $event = $this->pastEvent('Muy antiguo', 400);
        $this->register($event);
        $this->listen();

        $this->assertSame(1, $this->announcer()->announce());
    }

    public function test_many_events_are_all_published_once(): void
    {
        for ($i = 0; $i < 7; $i++) {
            $this->register($this->pastEvent('Evento '.$i, 10 + $i));
        }
        $this->listen();

        $this->assertSame(7, $this->announcer()->announce());
        Event::assertDispatchedTimes(ActivityRecorded::class, 7);
        $this->assertSame(0, $this->announcer()->announce());
    }

    public function test_the_publication_waits_for_the_commit(): void
    {
        $this->register($this->pastEvent());
        $this->listen();

        DB::transaction(function (): void {
            $this->announcer()->announce();

            Event::assertNotDispatched(ActivityRecorded::class);
        });

        Event::assertDispatchedTimes(ActivityRecorded::class, 1);
    }

    public function test_the_command_publishes_the_activity_and_still_announces_the_surveys(): void
    {
        $withSurvey = $this->surveyableEvent('2030-06-10 08:00:00 UTC', '2030-06-10 11:00:00 UTC');
        $this->registrant($withSurvey);
        $this->register($this->pastEvent());
        $this->listen([ActivityRecorded::class, SurveySubjectEnded::class]);

        $command = $this->artisan('event-registrations:announce-ended-events');
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->expectsOutput('Announced 1 ended event(s).')
            ->expectsOutput('Published the activity of 2 ended event(s).')
            ->assertSuccessful()
            ->run();

        Event::assertDispatchedTimes(SurveySubjectEnded::class, 1);
        Event::assertDispatchedTimes(ActivityRecorded::class, 2);
    }

    private function announcer(): EndedEventActivityAnnouncer
    {
        return $this->app->make(EndedEventActivityAnnouncer::class);
    }

    /**
     * Listens from here on, so what the setup did is not counted.
     *
     * @param  list<class-string>  $events
     */
    private function listen(array $events = [ActivityRecorded::class]): void
    {
        Event::fake($events);
        // The publisher is scoped and keeps the dispatcher it was built with
        $this->app->forgetInstance(ActivityPublisherInterface::class);
    }
}
