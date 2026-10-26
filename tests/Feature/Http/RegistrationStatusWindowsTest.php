<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Feature\Http;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationConfigModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * `is_open` (registering now) and `can_cancel` (cancelling now) of the public status are
 * separate windows; the start of the event closes both.
 */
final class RegistrationStatusWindowsTest extends ModuleTestCase
{
    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Away from the nights the clocks change (RegistrationAcrossDaylightSavingTest)
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00'));
    }

    public function test_before_the_start_registration_and_cancellation_are_open(): void
    {
        $event = $this->eventStarting(now()->addDay());

        $this->getJson("/eventos/{$event->id}/inscripcion/estado")
            ->assertOk()
            ->assertJsonPath('data.is_open', true)
            ->assertJsonPath('data.can_cancel', true)
            ->assertJsonPath('data.cancellation_closed_reason', null);
    }

    public function test_once_the_event_has_started_both_are_closed_whatever_the_dates_say(): void
    {
        $event = $this->eventStarting(now()->subMinute(), [
            'registration_closes_at' => now()->addDay(),
            'cancellation_deadline' => now()->addDay(),
        ]);

        $this->getJson("/eventos/{$event->id}/inscripcion/estado")
            ->assertOk()
            ->assertJsonPath('data.is_open', false)
            ->assertJsonPath('data.can_cancel', false)
            ->assertJsonPath('data.cancellation_closed_reason', 'event_started');
    }

    public function test_once_the_event_has_ended_its_end_is_the_closed_reason(): void
    {
        $event = $this->eventStarting(now()->subDay());

        $this->getJson("/eventos/{$event->id}/inscripcion/estado")
            ->assertOk()
            ->assertJsonPath('data.can_cancel', false)
            ->assertJsonPath('data.cancellation_closed_reason', 'event_ended');
    }

    public function test_cancellation_stays_open_after_registration_closes(): void
    {
        $event = $this->eventStarting(now()->addDay(), ['registration_closes_at' => now()->subHour()]);

        $this->getJson("/eventos/{$event->id}/inscripcion/estado")
            ->assertOk()
            ->assertJsonPath('data.is_open', false)
            ->assertJsonPath('data.can_cancel', true);
    }

    public function test_cancellation_closes_at_its_deadline_while_registration_is_open(): void
    {
        $event = $this->eventStarting(now()->addDay(), ['cancellation_deadline' => now()->subHour()]);

        $this->getJson("/eventos/{$event->id}/inscripcion/estado")
            ->assertOk()
            ->assertJsonPath('data.is_open', true)
            ->assertJsonPath('data.can_cancel', false)
            ->assertJsonPath('data.cancellation_closed_reason', 'deadline_passed');
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function eventStarting(CarbonInterface $startsAt, array $config = []): EventModel
    {
        $event = EventModel::factory()->published()->create([
            'start_date' => $startsAt,
            'end_date' => $startsAt->copy()->addHours(4),
        ]);

        EventRegistrationConfigModel::create([
            'event_id' => $event->id,
            'registration_enabled' => true,
            ...$config,
        ]);

        return $event;
    }
}
