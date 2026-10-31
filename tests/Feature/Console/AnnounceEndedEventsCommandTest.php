<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Feature\Console;

use App\Domain\Surveys\Events\SurveySubjectEnded;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\PendingCommand;
use Modules\EventRegistrations\Console\Commands\AnnounceEndedEventsCommand;
use Modules\EventRegistrations\Domain\Services\Clock;
use Modules\EventRegistrations\Tests\Support\BuildsSurveyableEvents;
use Modules\EventRegistrations\Tests\Support\FixedClock;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The scheduled command that tells the surveys module which events have ended.
 */
final class AnnounceEndedEventsCommandTest extends ModuleTestCase
{
    use BuildsSurveyableEvents;

    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useUtcStorage();
        Event::fake([SurveySubjectEnded::class]);
        $this->app->instance(Clock::class, FixedClock::at('2030-01-05T12:00:00+00:00'));
        $this->app->make(Kernel::class)->registerCommand($this->app->make(AnnounceEndedEventsCommand::class));
    }

    public function test_the_command_reports_how_many_it_announced(): void
    {
        $this->registrant($this->surveyableEvent('2030-01-05T08:00:00+00:00', '2030-01-05T11:00:00+00:00'));
        $this->registrant($this->surveyableEvent('2030-01-04T08:00:00+00:00', '2030-01-04T11:00:00+00:00'));

        $command = $this->artisan('event-registrations:announce-ended-events');
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->expectsOutput('Announced 2 ended event(s).')->assertSuccessful()->run();

        Event::assertDispatchedTimes(SurveySubjectEnded::class, 2);
    }

    public function test_the_command_is_scheduled_every_fifteen_minutes_without_overlapping(): void
    {
        $events = array_values(array_filter(
            app(Schedule::class)->events(),
            fn (ScheduledEvent $event): bool => str_contains((string) $event->command, 'event-registrations:announce-ended-events'),
        ));

        $this->assertCount(1, $events);
        $this->assertSame('*/15 * * * *', $events[0]->expression);
        $this->assertTrue($events[0]->withoutOverlapping);
    }
}
