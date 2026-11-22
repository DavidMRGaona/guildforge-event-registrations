<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Integration\Activity;

use App\Application\Activity\Services\ActivityPublisherInterface;
use App\Domain\Activity\Events\ActivityRecorded;
use App\Domain\Surveys\Events\SurveySubjectEnded;
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
        DB::table(EndedEventActivityAnnouncer::TABLE)->insert(['event_id' => $event->id, 'announced_at' => '2030-06-05 16:15:00']);
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
