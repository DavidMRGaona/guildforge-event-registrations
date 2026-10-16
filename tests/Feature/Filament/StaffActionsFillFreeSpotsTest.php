<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Feature\Filament;

use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Filament\RelationManagers\RegistrationsRelationManager;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationConfigModel;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationModel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Every panel action that frees a confirmed spot fills it from the waiting list, before the
 * event starts only.
 */
final class StaffActionsFillFreeSpotsTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->registerOnAdminPanel();
        $this->actingAsPanelAdmin();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function actionsThatFreeASpot(): array
    {
        return [
            'reject' => ['reject'],
            'reject selected' => ['reject_selected'],
            'move to waiting list' => ['move_to_waiting'],
            'edit the state to pending' => ['edit'],
            'delete' => ['delete'],
            'delete selected' => ['delete_selected'],
        ];
    }

    #[DataProvider('actionsThatFreeASpot')]
    public function test_a_staff_action_that_frees_a_spot_promotes_the_first_in_the_waiting_list(string $action): void
    {
        $event = $this->eventWithSpots(now()->addWeek());
        $confirmed = $this->registration($event, RegistrationState::Confirmed);
        $first = $this->registration($event, RegistrationState::WaitingList, 1);
        $second = $this->registration($event, RegistrationState::WaitingList, 2);

        $this->runStaffAction($action, $event, $confirmed);

        $this->assertSame(RegistrationState::Confirmed, $first->fresh()?->state);
        $this->assertSame(RegistrationState::WaitingList, $second->fresh()?->state);
        $this->assertSame(1, $second->fresh()?->position);
    }

    #[DataProvider('actionsThatFreeASpot')]
    public function test_no_staff_action_promotes_once_the_event_has_started(string $action): void
    {
        $event = $this->eventWithSpots(now()->subMinute());
        $confirmed = $this->registration($event, RegistrationState::Confirmed);
        $first = $this->registration($event, RegistrationState::WaitingList, 1);

        $this->runStaffAction($action, $event, $confirmed);

        $this->assertSame(RegistrationState::WaitingList, $first->fresh()?->state);
        $this->assertSame(1, $first->fresh()?->position);
    }

    #[DataProvider('actionsThatFreeASpot')]
    public function test_no_staff_action_promotes_when_the_waiting_list_is_disabled(string $action): void
    {
        $event = $this->eventWithSpots(now()->addWeek(), waitingListEnabled: false);
        $confirmed = $this->registration($event, RegistrationState::Confirmed);
        $first = $this->registration($event, RegistrationState::WaitingList, 1);

        $this->runStaffAction($action, $event, $confirmed);

        $this->assertSame(RegistrationState::WaitingList, $first->fresh()?->state);
    }

    public function test_rejecting_two_confirmed_registrations_promotes_two(): void
    {
        $event = $this->eventWithSpots(now()->addWeek(), maxParticipants: 2);
        $confirmed = [
            $this->registration($event, RegistrationState::Confirmed),
            $this->registration($event, RegistrationState::Confirmed),
        ];
        $first = $this->registration($event, RegistrationState::WaitingList, 1);
        $second = $this->registration($event, RegistrationState::WaitingList, 2);
        $third = $this->registration($event, RegistrationState::WaitingList, 3);

        $this->table($event)->callTableBulkAction('reject_selected', $confirmed)->assertHasNoErrors();

        $this->assertSame(RegistrationState::Confirmed, $first->fresh()?->state);
        $this->assertSame(RegistrationState::Confirmed, $second->fresh()?->state);
        $this->assertSame(RegistrationState::WaitingList, $third->fresh()?->state);
        $this->assertSame(1, $third->fresh()?->position);
    }

    public function test_a_registration_moved_behind_others_is_not_promoted_back(): void
    {
        $event = $this->eventWithSpots(now()->addWeek());
        $moved = $this->registration($event, RegistrationState::Confirmed);
        $waiting = $this->registration($event, RegistrationState::WaitingList, 1);

        $this->runStaffAction('move_to_waiting', $event, $moved);

        $this->assertSame(RegistrationState::Confirmed, $waiting->fresh()?->state);
        $this->assertSame(RegistrationState::WaitingList, $moved->fresh()?->state);
        $this->assertSame(1, $moved->fresh()?->position);
    }

    public function test_a_registration_moved_to_an_empty_waiting_list_stays_there_and_the_spot_stays_free(): void
    {
        $event = $this->eventWithSpots(now()->addWeek());
        $moved = $this->registration($event, RegistrationState::Confirmed);

        $this->runStaffAction('move_to_waiting', $event, $moved);

        $this->assertSame(RegistrationState::WaitingList, $moved->fresh()?->state);
        $this->assertSame(1, $moved->fresh()?->position);
        $this->assertSame(0, EventRegistrationModel::query()->where('state', RegistrationState::Confirmed)->count());
    }

    public function test_a_registration_moved_on_an_event_without_a_limit_stays_on_the_waiting_list(): void
    {
        $event = $this->eventWithSpots(now()->addWeek(), maxParticipants: null);
        $moved = $this->registration($event, RegistrationState::Confirmed);

        $this->runStaffAction('move_to_waiting', $event, $moved);

        $this->assertSame(RegistrationState::WaitingList, $moved->fresh()?->state);
        $this->assertSame(1, $moved->fresh()?->position);
    }

    public function test_editing_the_state_to_waiting_list_promotes_the_other_person_and_keeps_the_edited_one_waiting(): void
    {
        $event = $this->eventWithSpots(now()->addWeek());
        $edited = $this->registration($event, RegistrationState::Confirmed);
        $other = $this->registration($event, RegistrationState::WaitingList, 1);

        $this->table($event)
            ->callTableAction('edit', $edited, data: ['state' => RegistrationState::WaitingList->value])
            ->assertHasNoErrors();

        $this->assertSame(RegistrationState::Confirmed, $other->fresh()?->state);
        $this->assertSame(RegistrationState::WaitingList, $edited->fresh()?->state);
        $this->assertNotNull($edited->fresh()?->position);
    }

    private function runStaffAction(string $action, EventModel $event, EventRegistrationModel $record): void
    {
        $table = $this->table($event);

        match ($action) {
            'reject', 'move_to_waiting', 'delete' => $table->callTableAction($action, $record),
            'edit' => $table->callTableAction('edit', $record, data: ['state' => RegistrationState::Pending->value]),
            'reject_selected' => $table->callTableBulkAction('reject_selected', [$record]),
            'delete_selected' => $table->callTableBulkAction('delete', [$record]),
        };

        $table->assertHasNoErrors();
    }

    private function table(EventModel $event): Testable
    {
        return Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $event, 'pageClass' => EditEvent::class]);
    }

    private function eventWithSpots(
        CarbonInterface $startsAt,
        bool $waitingListEnabled = true,
        ?int $maxParticipants = 1,
    ): EventModel {
        $event = EventModel::factory()->create([
            'start_date' => $startsAt,
            'end_date' => $startsAt->copy()->addHours(4),
        ]);

        EventRegistrationConfigModel::create([
            'event_id' => $event->id,
            'registration_enabled' => true,
            'max_participants' => $maxParticipants,
            'waiting_list_enabled' => $waitingListEnabled,
        ]);

        return $event;
    }

    private function registration(EventModel $event, RegistrationState $state, ?int $position = null): EventRegistrationModel
    {
        return EventRegistrationModel::create([
            'event_id' => $event->id,
            'user_id' => UserModel::factory()->create()->id,
            'state' => $state,
            'position' => $position,
            'confirmed_at' => $state === RegistrationState::Confirmed ? now() : null,
        ]);
    }
}
