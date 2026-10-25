<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Feature\Http;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\Notification;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Domain\Services\Clock;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationConfigModel;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationModel;
use Modules\EventRegistrations\Tests\Support\FixedClock;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The start of the event and the deadlines are compared as instants on the two nights the clocks
 * change in Europe/Madrid. On 25 October 2026 the hour from 02:00 is lived twice (00:00Z with
 * CEST, 01:00Z with CET); on 29 March 2026 the clocks jump from 02:00 CET to 03:00 CEST at 01:00Z.
 * Dates are stored as Madrid wall times, so these use times outside the ambiguous hour.
 */
final class RegistrationAcrossDaylightSavingTest extends ModuleTestCase
{
    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    /** 02:15 CEST, first pass of the repeated hour. */
    private const string OCTOBER_FIRST_PASS = '2026-10-25T00:15:00Z';

    /** 02:15 CET, second pass of the repeated hour. */
    private const string OCTOBER_SECOND_PASS = '2026-10-25T01:15:00Z';

    /** 01:59 CET, the last minute before the jump. */
    private const string MARCH_BEFORE_JUMP = '2026-03-29T00:59:00Z';

    /** 03:00 CEST, the first minute after the jump. */
    private const string MARCH_AFTER_JUMP = '2026-03-29T01:00:00Z';

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_an_event_after_the_change_is_open_during_the_first_pass_of_the_repeated_hour(): void
    {
        $this->clockAt(self::OCTOBER_FIRST_PASS);
        $event = $this->eventStarting('2026-10-25 03:30');

        $this->actingAs(UserModel::factory()->create())
            ->postJson("/eventos/{$event->id}/inscripcion")
            ->assertCreated();
    }

    public function test_an_event_after_the_change_is_open_during_the_second_pass_of_the_repeated_hour(): void
    {
        $this->clockAt(self::OCTOBER_SECOND_PASS);
        $event = $this->eventStarting('2026-10-25 03:30');

        $this->actingAs(UserModel::factory()->create())
            ->postJson("/eventos/{$event->id}/inscripcion")
            ->assertCreated();
    }

    public function test_an_event_that_started_before_the_change_is_closed_during_the_repeated_hour(): void
    {
        $this->clockAt(self::OCTOBER_FIRST_PASS);
        $event = $this->eventStarting('2026-10-25 01:30');

        $this->actingAs(UserModel::factory()->create())
            ->postJson("/eventos/{$event->id}/inscripcion")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'El evento ya ha comenzado');
    }

    public function test_the_status_during_the_repeated_hour_reads_the_start_as_an_instant(): void
    {
        $this->clockAt(self::OCTOBER_FIRST_PASS);
        $event = $this->eventStarting('2026-10-25 03:30');

        $this->getJson("/eventos/{$event->id}/inscripcion/estado")
            ->assertOk()
            ->assertJsonPath('data.is_open', true)
            ->assertJsonPath('data.can_cancel', true);
    }

    public function test_cancelling_during_the_repeated_hour_before_an_event_after_the_change_works(): void
    {
        $this->clockAt(self::OCTOBER_FIRST_PASS);
        $event = $this->eventStarting('2026-10-25 03:30');
        $user = UserModel::factory()->create();
        $registration = $this->confirmedRegistration($event, $user);

        $this->actingAs($user)
            ->deleteJson("/eventos/{$event->id}/inscripcion")
            ->assertOk();

        $this->assertSame(RegistrationState::Cancelled, $registration->fresh()?->state);
    }

    public function test_a_cancellation_deadline_passed_before_the_change_is_enforced_during_the_repeated_hour(): void
    {
        $this->clockAt(self::OCTOBER_FIRST_PASS);
        $event = $this->eventStarting('2026-10-25 03:30', ['cancellation_deadline' => $this->madrid('2026-10-25 01:45')]);
        $user = UserModel::factory()->create();
        $this->confirmedRegistration($event, $user);

        $this->actingAs($user)
            ->deleteJson("/eventos/{$event->id}/inscripcion")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'No se puede cancelar la inscripción');
    }

    public function test_an_event_right_after_the_jump_is_open_the_minute_before(): void
    {
        $this->clockAt(self::MARCH_BEFORE_JUMP);
        $event = $this->eventStarting('2026-03-29 03:00');

        $this->actingAs(UserModel::factory()->create())
            ->postJson("/eventos/{$event->id}/inscripcion")
            ->assertCreated();
    }

    public function test_an_event_right_after_the_jump_closes_when_it_starts(): void
    {
        $this->clockAt(self::MARCH_AFTER_JUMP);
        $event = $this->eventStarting('2026-03-29 03:00');

        $this->actingAs(UserModel::factory()->create())
            ->postJson("/eventos/{$event->id}/inscripcion")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'El evento ya ha comenzado');
    }

    private function clockAt(string $instant): void
    {
        $this->app->instance(Clock::class, FixedClock::at($instant));
    }

    private function madrid(string $wallTime): DateTimeImmutable
    {
        return new DateTimeImmutable($wallTime, new DateTimeZone('Europe/Madrid'));
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function eventStarting(string $wallTime, array $config = []): EventModel
    {
        $startsAt = $this->madrid($wallTime);

        $event = EventModel::factory()->published()->create([
            'start_date' => $startsAt,
            'end_date' => $startsAt->modify('+4 hours'),
        ]);

        EventRegistrationConfigModel::create([
            'event_id' => $event->id,
            'registration_enabled' => true,
            ...$config,
        ]);

        return $event;
    }

    private function confirmedRegistration(EventModel $event, UserModel $user): EventRegistrationModel
    {
        return EventRegistrationModel::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'state' => RegistrationState::Confirmed,
            'confirmed_at' => $this->madrid('2026-10-20 18:00'),
        ]);
    }
}
