<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Feature\Console;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Notification;
use Modules\EventRegistrations\Console\Commands\SendEventRemindersCommand;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationModel;
use Modules\EventRegistrations\Notifications\EventReminderNotification;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The reminder before an event is ready but off until enabled (send_reminder_email).
 */
final class SendEventRemindersCommandTest extends ModuleTestCase
{
    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('es');
        Notification::fake();
        $this->now = CarbonImmutable::parse('2026-11-02 10:00:00');
        $this->travelTo($this->now);
        $this->app->make(Kernel::class)->registerCommand($this->app->make(SendEventRemindersCommand::class));
    }

    public function test_nothing_is_sent_while_reminders_are_off(): void
    {
        [$user, $registration] = $this->registered($this->event($this->now->addHours(10)), RegistrationState::Confirmed);

        $this->artisan('event-registrations:send-reminders')->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertNull($registration->fresh()?->reminder_sent_at);
    }

    public function test_confirmed_registrations_of_an_event_starting_soon_are_reminded_once(): void
    {
        $this->enable();
        $event = $this->event($this->now->addHours(10));
        [$confirmed] = $this->registered($event, RegistrationState::Confirmed);
        [$waiting] = $this->registered($event, RegistrationState::WaitingList, 1);
        [$pending] = $this->registered($event, RegistrationState::Pending);
        [$cancelled] = $this->registered($event, RegistrationState::Cancelled);

        $this->artisan('event-registrations:send-reminders')->assertSuccessful();
        $this->artisan('event-registrations:send-reminders')->assertSuccessful();

        Notification::assertSentToTimes($confirmed, EventReminderNotification::class, 1);
        Notification::assertNotSentTo([$waiting, $pending, $cancelled], EventReminderNotification::class);
        Notification::assertSentTo($confirmed, EventReminderNotification::class, function (EventReminderNotification $n) use ($confirmed, $event): bool {
            $message = $n->toMail($confirmed);

            return $message->subject === 'Recordatorio: '.$event->title.', 02/11/2026 20:00'
                && $message->actionUrl === url('/eventos/'.$event->slug);
        });
    }

    public function test_events_outside_the_window_are_skipped(): void
    {
        $this->enable();
        [$later] = $this->registered($this->event($this->now->addHours(30)), RegistrationState::Confirmed);
        [$started] = $this->registered($this->event($this->now->subHour()), RegistrationState::Confirmed);

        $this->artisan('event-registrations:send-reminders')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_the_lead_time_is_configurable(): void
    {
        $this->enable(hours: 48);
        [$user] = $this->registered($this->event($this->now->addHours(30)), RegistrationState::Confirmed);

        $this->artisan('event-registrations:send-reminders')->assertSuccessful();

        Notification::assertSentTo($user, EventReminderNotification::class);
    }

    public function test_the_command_is_scheduled_hourly_without_overlapping(): void
    {
        $events = array_values(array_filter(
            app(Schedule::class)->events(),
            fn (ScheduledEvent $event): bool => str_contains((string) $event->command, 'event-registrations:send-reminders'),
        ));

        $this->assertCount(1, $events);
        $this->assertSame('0 * * * *', $events[0]->expression);
        $this->assertTrue($events[0]->withoutOverlapping);
    }

    private function enable(int $hours = 24): void
    {
        config()->set('modules.settings.event-registrations.send_reminder_email', true);
        config()->set('modules.settings.event-registrations.reminder_hours_before', $hours);
    }

    private function event(CarbonImmutable $startsAt): EventModel
    {
        return EventModel::factory()->published()->create([
            'start_date' => $startsAt,
            'end_date' => $startsAt->addHours(4),
        ]);
    }

    /**
     * @return array{UserModel, EventRegistrationModel}
     */
    private function registered(EventModel $event, RegistrationState $state, ?int $position = null): array
    {
        $user = UserModel::factory()->create();

        return [$user, EventRegistrationModel::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'state' => $state,
            'position' => $position,
            'confirmed_at' => $state === RegistrationState::Confirmed ? $this->now->subDay() : null,
        ])];
    }
}
