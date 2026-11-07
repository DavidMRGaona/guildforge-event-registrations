<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Feature\Http;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Notification;
use Modules\EventRegistrations\Application\Services\WaitingListServiceInterface;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationConfigModel;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * A maximum of 0 participants means no limit, as an empty maximum does: the event is never
 * full, registering confirms and the waiting list empties when spots are filled.
 */
final class ZeroMaxParticipantsTest extends ModuleTestCase
{
    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    private EventModel $event;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->event = EventModel::factory()->published()->create(['start_date' => now()->addWeek()]);
        EventRegistrationConfigModel::create([
            'event_id' => $this->event->id,
            'registration_enabled' => true,
            'max_participants' => 0,
            'waiting_list_enabled' => true,
            'requires_confirmation' => false,
        ]);
    }

    public function test_the_status_of_an_event_with_a_maximum_of_zero_is_not_full(): void
    {
        $this->createRegistration(RegistrationState::Confirmed);
        $this->createRegistration(RegistrationState::Confirmed);

        $this->getJson("/eventos/{$this->event->id}/inscripcion/estado")
            ->assertOk()
            ->assertJsonPath('data.is_full', false)
            ->assertJsonPath('data.max_participants', null)
            ->assertJsonPath('data.available_spots', -1)
            ->assertJsonPath('data.current_participants', 2);
    }

    public function test_registering_to_an_event_with_a_maximum_of_zero_confirms(): void
    {
        $this->createRegistration(RegistrationState::Confirmed);

        $this->actingAs(UserModel::factory()->create())
            ->postJson("/eventos/{$this->event->id}/inscripcion")
            ->assertCreated()
            ->assertJsonPath('data.state', RegistrationState::Confirmed->value);
    }

    public function test_filling_free_spots_with_a_maximum_of_zero_promotes_everyone_waiting(): void
    {
        $this->createRegistration(RegistrationState::Confirmed);
        $first = $this->createRegistration(RegistrationState::WaitingList, 1);
        $second = $this->createRegistration(RegistrationState::WaitingList, 2);

        $promoted = $this->app->make(WaitingListServiceInterface::class)->fillFreeSpots((string) $this->event->id);

        $this->assertSame(2, $promoted);
        $this->assertSame(RegistrationState::Confirmed, $first->fresh()?->state);
        $this->assertSame(RegistrationState::Confirmed, $second->fresh()?->state);
    }

    private function createRegistration(RegistrationState $state, ?int $position = null): EventRegistrationModel
    {
        return EventRegistrationModel::create([
            'event_id' => $this->event->id,
            'user_id' => UserModel::factory()->create()->id,
            'state' => $state,
            'position' => $position,
            'confirmed_at' => $state === RegistrationState::Confirmed ? now() : null,
        ]);
    }
}
