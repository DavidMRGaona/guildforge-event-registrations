<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Feature\Filament;

use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Domain\Events\WaitingListPromoted;
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
        $this->assertSame(1, $edited->fresh()?->position);
    }

    public function test_editing_only_the_notes_of_a_waiting_registration_does_not_make_it_jump_the_queue(): void
    {
        // Limit raised to 2 with one confirmed: a free spot sits next to the waiting list
        $event = $this->eventWithSpots(now()->addWeek(), maxParticipants: 2);
        $this->registration($event, RegistrationState::Confirmed);
        $first = $this->registration($event, RegistrationState::WaitingList, 1);
        $second = $this->registration($event, RegistrationState::WaitingList, 2);

        $this->table($event)
            ->callTableAction('edit', $first, data: ['state' => RegistrationState::WaitingList->value, 'admin_notes' => 'Llamar antes'])
            ->assertHasNoErrors();

        $this->assertSame(RegistrationState::Confirmed, $first->fresh()?->state);
        $this->assertSame(RegistrationState::WaitingList, $second->fresh()?->state);
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function selectionOrders(): array
    {
        return ['confirmed listed first' => [true], 'waiting listed first' => [false]];
    }

    #[DataProvider('selectionOrders')]
    public function test_bulk_rejecting_a_confirmed_and_a_waiting_registration_never_promotes_the_rejected_one(bool $confirmedFirst): void
    {
        EventFacade::fake([WaitingListPromoted::class]);

        $event = $this->eventWithSpots(now()->addWeek());
        $confirmed = $this->registration($event, RegistrationState::Confirmed);
        $firstWaiting = $this->registration($event, RegistrationState::WaitingList, 1);
        $secondWaiting = $this->registration($event, RegistrationState::WaitingList, 2);

        $confirmed->forceFill(['created_at' => now()->subHours($confirmedFirst ? 1 : 3)])->save();
        $firstWaiting->forceFill(['created_at' => now()->subHours(2)])->save();

        $this->table($event)
            ->callTableBulkAction('reject_selected', [$confirmed, $firstWaiting])
            ->assertHasNoErrors();

        $this->assertSame(RegistrationState::Rejected, $confirmed->fresh()?->state);
        $this->assertSame(RegistrationState::Rejected, $firstWaiting->fresh()?->state);
        $this->assertSame(RegistrationState::Confirmed, $secondWaiting->fresh()?->state);
        EventFacade::assertDispatchedTimes(WaitingListPromoted::class, 1);
        EventFacade::assertDispatched(
            WaitingListPromoted::class,
            fn (WaitingListPromoted $e): bool => $e->registrationId === (string) $secondWaiting->id,
        );
    }

    #[DataProvider('actionsThatLeaveTheWaitingListWithoutAPromotion')]
    public function test_the_waiting_list_is_renumbered_when_someone_leaves_it_without_a_promotion(string $action): void
    {
        // The only spot stays taken, so nobody is promoted
        $event = $this->eventWithSpots(now()->addWeek());
        $this->registration($event, RegistrationState::Confirmed);
        $first = $this->registration($event, RegistrationState::WaitingList, 1);
        $second = $this->registration($event, RegistrationState::WaitingList, 2);
        $third = $this->registration($event, RegistrationState::WaitingList, 3);

        $this->runStaffAction($action, $event, $first);

        $this->assertSame(1, $second->fresh()?->position);
        $this->assertSame(2, $third->fresh()?->position);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function actionsThatLeaveTheWaitingListWithoutAPromotion(): array
    {
        return [
            'reject' => ['reject'],
            'reject selected' => ['reject_selected'],
            'delete' => ['delete'],
            'delete selected' => ['delete_selected'],
            'edit the state to pending' => ['edit'],
        ];
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
