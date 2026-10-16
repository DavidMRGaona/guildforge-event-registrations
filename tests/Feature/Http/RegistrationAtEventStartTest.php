<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Feature\Http;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Notification;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationConfigModel;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The start of the event closes registration and cancellation for users. The domain reads the
 * real clock, so starts sit a minute or more away from now; exact instants are unit tested.
 */
final class RegistrationAtEventStartTest extends ModuleTestCase
{
    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_registering_before_the_start_is_accepted(): void
    {
        $event = $this->eventStarting(now()->addMinutes(5));

        $this->actingAs(UserModel::factory()->create())
            ->postJson("/eventos/{$event->id}/inscripcion")
            ->assertCreated();
    }

    public function test_registering_once_the_event_has_started_is_refused(): void
    {
        $event = $this->eventStarting(now()->subMinute());

        $this->actingAs(UserModel::factory()->create())
            ->postJson("/eventos/{$event->id}/inscripcion")
            ->assertUnprocessable()
            ->assertJsonPath('message', __('event-registrations::messages.errors.event_started'));

        $this->assertSame(0, EventRegistrationModel::query()->count());
    }

    public function test_a_close_date_after_the_start_does_not_keep_registration_open(): void
    {
        $event = $this->eventStarting(now()->subMinute(), ['registration_closes_at' => now()->addDay()]);

        $this->actingAs(UserModel::factory()->create())
            ->postJson("/eventos/{$event->id}/inscripcion")
            ->assertUnprocessable()
            ->assertJsonPath('message', __('event-registrations::messages.errors.event_started'));
    }

    public function test_registering_again_after_cancelling_is_refused_once_the_event_has_started(): void
    {
        $event = $this->eventStarting(now()->subMinute());
        $user = UserModel::factory()->create();
        $registration = $this->registration($event, $user, RegistrationState::Cancelled);

        $this->actingAs($user)
            ->postJson("/eventos/{$event->id}/inscripcion")
            ->assertUnprocessable()
            ->assertJsonPath('message', __('event-registrations::messages.errors.event_started'));

        $this->assertSame(RegistrationState::Cancelled, $registration->fresh()?->state);
    }

    public function test_cancelling_once_the_event_has_started_is_refused(): void
    {
        $event = $this->eventStarting(now()->subMinute(), ['cancellation_deadline' => now()->addDay()]);
        $user = UserModel::factory()->create();
        $registration = $this->registration($event, $user, RegistrationState::Confirmed);

        $this->actingAs($user)
            ->deleteJson("/eventos/{$event->id}/inscripcion")
            ->assertUnprocessable()
            ->assertJsonPath('message', __('event-registrations::messages.errors.cannot_cancel_started'));

        $this->assertSame(RegistrationState::Confirmed, $registration->fresh()?->state);
    }

    public function test_leaving_the_waiting_list_once_the_event_has_started_is_refused(): void
    {
        $event = $this->eventStarting(now()->subMinute());
        $user = UserModel::factory()->create();
        $registration = $this->registration($event, $user, RegistrationState::WaitingList, position: 1);

        $this->actingAs($user)
            ->deleteJson("/eventos/{$event->id}/inscripcion")
            ->assertUnprocessable()
            ->assertJsonPath('message', __('event-registrations::messages.errors.cannot_cancel_started'));

        $this->assertSame(RegistrationState::WaitingList, $registration->fresh()?->state);
        $this->assertSame(1, $registration->fresh()?->position);
    }

    public function test_cancelling_before_the_start_works_after_registration_has_closed(): void
    {
        $event = $this->eventStarting(now()->addDay(), ['registration_closes_at' => now()->subHour()]);
        $user = UserModel::factory()->create();
        $registration = $this->registration($event, $user, RegistrationState::Confirmed);

        $this->actingAs($user)
            ->deleteJson("/eventos/{$event->id}/inscripcion")
            ->assertOk();

        $this->assertSame(RegistrationState::Cancelled, $registration->fresh()?->state);
    }

    public function test_a_passed_deadline_before_the_start_keeps_its_own_message(): void
    {
        $event = $this->eventStarting(now()->addDay(), ['cancellation_deadline' => now()->subHour()]);
        $user = UserModel::factory()->create();
        $this->registration($event, $user, RegistrationState::Confirmed);

        $this->actingAs($user)
            ->deleteJson("/eventos/{$event->id}/inscripcion")
            ->assertUnprocessable()
            ->assertJsonPath('message', __('event-registrations::messages.errors.cannot_cancel'));
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

    private function registration(
        EventModel $event,
        UserModel $user,
        RegistrationState $state,
        ?int $position = null,
    ): EventRegistrationModel {
        return EventRegistrationModel::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'state' => $state,
            'position' => $position,
            'confirmed_at' => $state === RegistrationState::Confirmed ? now() : null,
        ]);
    }
}
