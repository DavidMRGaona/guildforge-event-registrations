<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Feature\Filament;

use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\EventRegistrations\Application\Services\EventRegistrationServiceInterface;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Domain\Exceptions\EventFullException;
use Modules\EventRegistrations\Domain\Exceptions\InvalidRegistrationTransitionException;
use Modules\EventRegistrations\Filament\RelationManagers\RegistrationsRelationManager;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationConfigModel;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationModel;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Confirming and rejecting go through the domain transitions: only the states that may change
 * do, confirming respects the capacity and renumbers the waiting list, and the bulk actions
 * skip what cannot change and say how many they skipped.
 */
final class ConfirmAndRejectTransitionsTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    private EventModel $event;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        // Away from the nights the clocks change (RegistrationAcrossDaylightSavingTest)
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00'));
        $this->registerOnAdminPanel();
        $this->actingAsPanelAdmin();
        $this->event = EventModel::factory()->create([
            'start_date' => now()->addWeek(),
            'end_date' => now()->addWeek()->addHours(4),
        ]);
    }

    public function test_confirming_with_the_event_full_is_refused(): void
    {
        $this->config(maxParticipants: 1);
        $this->registration(RegistrationState::Confirmed);
        $pending = $this->registration(RegistrationState::Pending);

        try {
            $this->service()->confirm((string) $pending->id);
            $this->fail('A full event confirmed one more');
        } catch (EventFullException) {
            $this->assertSame(RegistrationState::Pending, $pending->fresh()?->state);
        }
    }

    public function test_confirming_a_cancelled_registration_is_refused(): void
    {
        $this->config(maxParticipants: null);
        $cancelled = $this->registration(RegistrationState::Cancelled);

        $this->expectException(InvalidRegistrationTransitionException::class);

        $this->service()->confirm((string) $cancelled->id);
    }

    public function test_confirming_from_the_waiting_list_moves_the_others_up(): void
    {
        $this->config(maxParticipants: 2);
        $this->registration(RegistrationState::Confirmed);
        $first = $this->registration(RegistrationState::WaitingList, 1);
        $second = $this->registration(RegistrationState::WaitingList, 2);
        $third = $this->registration(RegistrationState::WaitingList, 3);

        $this->service()->confirm((string) $second->id);

        $this->assertSame(RegistrationState::Confirmed, $second->fresh()?->state);
        $this->assertNull($second->fresh()->position);
        $this->assertSame(1, $first->fresh()?->position);
        $this->assertSame(2, $third->fresh()?->position);
    }

    public function test_confirming_many_fills_the_free_spots_waiting_list_first_and_skips_the_rest(): void
    {
        $this->config(maxParticipants: 4);
        $this->registration(RegistrationState::Confirmed);
        $alreadyConfirmed = $this->registration(RegistrationState::Confirmed);
        $olderPending = $this->registration(RegistrationState::Pending);
        $this->travel(1)->minutes();
        $newerPending = $this->registration(RegistrationState::Pending);
        $secondInLine = $this->registration(RegistrationState::WaitingList, 2);
        $firstInLine = $this->registration(RegistrationState::WaitingList, 1);
        $notSelected = $this->registration(RegistrationState::WaitingList, 3);
        $cancelled = $this->registration(RegistrationState::Cancelled);
        $rejected = $this->registration(RegistrationState::Rejected);

        $result = $this->service()->confirmMany(array_map(
            fn (EventRegistrationModel $registration): string => (string) $registration->id,
            [$newerPending, $olderPending, $secondInLine, $firstInLine, $alreadyConfirmed, $cancelled, $rejected],
        ));

        // Two free spots: the waiting list by its position goes first
        $this->assertSame(2, $result->changed);
        $this->assertSame(3, $result->skippedInvalid);
        $this->assertSame(2, $result->skippedFull);
        $this->assertSame(RegistrationState::Confirmed, $firstInLine->fresh()?->state);
        $this->assertSame(RegistrationState::Confirmed, $secondInLine->fresh()?->state);
        $this->assertSame(RegistrationState::Pending, $olderPending->fresh()?->state);
        $this->assertSame(RegistrationState::Pending, $newerPending->fresh()?->state);
        $this->assertSame(RegistrationState::Cancelled, $cancelled->fresh()?->state);
        $this->assertSame(RegistrationState::Rejected, $rejected->fresh()?->state);
        $this->assertSame(1, $notSelected->fresh()?->position);
    }

    public function test_confirming_many_takes_the_older_pending_registration_first(): void
    {
        $this->config(maxParticipants: 1);
        $olderPending = $this->registration(RegistrationState::Pending);
        $this->travel(1)->minutes();
        $newerPending = $this->registration(RegistrationState::Pending);

        $result = $this->service()->confirmMany([(string) $newerPending->id, (string) $olderPending->id]);

        $this->assertSame(1, $result->changed);
        $this->assertSame(1, $result->skippedFull);
        $this->assertSame(RegistrationState::Confirmed, $olderPending->fresh()?->state);
        $this->assertSame(RegistrationState::Pending, $newerPending->fresh()?->state);
    }

    public function test_confirming_many_without_a_limit_confirms_every_one_that_can(): void
    {
        $this->config(maxParticipants: null);
        $pending = $this->registration(RegistrationState::Pending);
        $waiting = $this->registration(RegistrationState::WaitingList, 1);

        $result = $this->service()->confirmMany([(string) $pending->id, (string) $waiting->id]);

        $this->assertSame(2, $result->changed);
        $this->assertSame(0, $result->skippedFull);
    }

    public function test_rejecting_many_skips_the_cancelled_ones(): void
    {
        $this->config(maxParticipants: null);
        $pending = $this->registration(RegistrationState::Pending);
        $cancelled = $this->registration(RegistrationState::Cancelled);
        $rejected = $this->registration(RegistrationState::Rejected);

        $result = $this->service()->rejectMany([(string) $pending->id, (string) $cancelled->id, (string) $rejected->id]);

        $this->assertSame(1, $result->changed);
        $this->assertSame(2, $result->skippedInvalid);
        $this->assertSame(RegistrationState::Rejected, $pending->fresh()?->state);
        $this->assertSame(RegistrationState::Cancelled, $cancelled->fresh()?->state);
    }

    public function test_the_confirm_action_says_the_event_is_full(): void
    {
        $this->config(maxParticipants: 1);
        $this->registration(RegistrationState::Confirmed);
        $pending = $this->registration(RegistrationState::Pending);

        $this->table()
            ->callTableAction('confirm', $pending)
            ->assertNotified(FilamentNotification::make()->title('No quedan plazas libres: el evento está completo.')->warning());

        $this->assertSame(RegistrationState::Pending, $pending->fresh()?->state);
    }

    public function test_the_confirm_selected_action_says_how_many_it_confirmed_and_skipped(): void
    {
        $this->config(maxParticipants: 2);
        $this->registration(RegistrationState::Confirmed);
        $waiting = $this->registration(RegistrationState::WaitingList, 1);
        $pending = $this->registration(RegistrationState::Pending);
        $cancelled = $this->registration(RegistrationState::Cancelled);
        $rejected = $this->registration(RegistrationState::Rejected);

        $this->table()
            ->callTableBulkAction('confirm_selected', [$waiting, $pending, $cancelled, $rejected])
            ->assertNotified(
                FilamentNotification::make()
                    ->title('Inscripciones confirmadas')
                    ->body('Se ha confirmado 1 inscripción. Se han saltado 2 que no se podían confirmar (canceladas, rechazadas o ya confirmadas). Se ha saltado 1 por falta de plazas.')
                    ->success()
            );
    }

    public function test_the_confirm_selected_action_warns_when_it_confirmed_none(): void
    {
        $this->config(maxParticipants: 1);
        $this->registration(RegistrationState::Confirmed);
        $pending = $this->registration(RegistrationState::Pending);

        $this->table()
            ->callTableBulkAction('confirm_selected', [$pending])
            ->assertNotified(
                FilamentNotification::make()
                    ->title('No hay inscripciones para confirmar')
                    ->body('Se ha saltado 1 por falta de plazas.')
                    ->warning()
            );
    }

    public function test_the_reject_selected_action_says_how_many_it_rejected_and_skipped(): void
    {
        $this->config(maxParticipants: null);
        $pending = $this->registration(RegistrationState::Pending);
        $confirmed = $this->registration(RegistrationState::Confirmed);
        $cancelled = $this->registration(RegistrationState::Cancelled);

        $this->table()
            ->callTableBulkAction('reject_selected', [$pending, $confirmed, $cancelled])
            ->assertNotified(
                FilamentNotification::make()
                    ->title('Inscripciones rechazadas')
                    ->body('Se han rechazado 2 inscripciones. Se ha saltado 1 que no se podía rechazar (cancelada o ya rechazada).')
                    ->success()
            );
    }

    private function service(): EventRegistrationServiceInterface
    {
        return $this->app->make(EventRegistrationServiceInterface::class);
    }

    /**
     * @return Testable<RegistrationsRelationManager>
     */
    private function table(): Testable
    {
        return Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $this->event, 'pageClass' => EditEvent::class]);
    }

    private function config(?int $maxParticipants): void
    {
        EventRegistrationConfigModel::create([
            'event_id' => $this->event->id,
            'registration_enabled' => true,
            'max_participants' => $maxParticipants,
            'waiting_list_enabled' => true,
        ]);
    }

    private function registration(RegistrationState $state, ?int $position = null): EventRegistrationModel
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
