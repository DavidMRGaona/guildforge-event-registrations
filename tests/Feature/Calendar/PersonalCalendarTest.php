<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Feature\Calendar;

use App\Application\Calendar\Services\PersonalCalendarServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The module feeds the core's personal calendar: the «only mine» filter shows the events a person
 * is confirmed in and nothing else.
 */
final class PersonalCalendarTest extends ModuleTestCase
{
    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useUtcStorage();
    }

    public function test_the_personal_calendar_is_available_with_the_module(): void
    {
        $this->assertTrue($this->app->make(PersonalCalendarServiceInterface::class)->isAvailable());
    }

    public function test_only_mine_shows_confirmed_events_and_nothing_else(): void
    {
        $user = UserModel::factory()->create();
        $other = UserModel::factory()->create();
        $confirmed = $this->event('Confirmado', '2030-03-10 10:00:00');
        $pending = $this->event('Pendiente', '2030-03-11 10:00:00');
        $waiting = $this->event('En espera', '2030-03-12 10:00:00');
        $cancelled = $this->event('Cancelado', '2030-03-13 10:00:00');
        $rejected = $this->event('Rechazado', '2030-03-14 10:00:00');
        $someoneElses = $this->event('De otra persona', '2030-03-15 10:00:00');
        $this->event('Sin inscripción', '2030-03-16 10:00:00');
        $this->register($confirmed, $user, RegistrationState::Confirmed);
        $this->register($pending, $user, RegistrationState::Pending);
        $this->register($waiting, $user, RegistrationState::WaitingList);
        $this->register($cancelled, $user, RegistrationState::Cancelled);
        $this->register($rejected, $user, RegistrationState::Rejected);
        $this->register($someoneElses, $other, RegistrationState::Confirmed);

        $response = $this->actingAs($user)->getJson('/eventos/calendario?start=2030-03-01&end=2030-04-01&mine=1');

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['title' => 'Confirmado']);
    }

    public function test_the_feed_source_returns_the_confirmed_event(): void
    {
        $user = UserModel::factory()->create();
        $event = $this->event('Confirmado', '2030-03-10 10:00:00');
        $this->register($event, $user, RegistrationState::Confirmed);
        $this->register($this->event('Pendiente', '2030-03-11 10:00:00'), $user, RegistrationState::Pending);

        $entries = $this->app->make(PersonalCalendarServiceInterface::class)->entriesFor(
            $user->id,
            new DateTimeImmutable('2030-03-01'),
            new DateTimeImmutable('2030-04-01'),
        );

        $this->assertCount(1, $entries);
        $this->assertSame($event->id, $entries[0]->id);
    }

    private function event(string $title, string $start): EventModel
    {
        return EventModel::factory()->published()->create([
            'title' => $title,
            'start_date' => new DateTimeImmutable($start),
            'end_date' => new DateTimeImmutable($start.' +2 hours'),
        ]);
    }

    private function register(EventModel $event, UserModel $user, RegistrationState $state): void
    {
        EventRegistrationModel::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'state' => $state,
        ]);
    }
}
