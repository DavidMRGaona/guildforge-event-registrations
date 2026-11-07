<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Feature\Filament;

use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\EventRegistrations\Application\Services\EventRegistrationServiceInterface;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Domain\Events\RegistrationRejected;
use Modules\EventRegistrations\Filament\RelationManagers\RegistrationsRelationManager;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationConfigModel;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationModel;
use Modules\EventRegistrations\Notifications\RegistrationRejectedNotification;
use RuntimeException;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Every way of rejecting a registration announces it once, after the commit, and the person
 * gets the rejection email once.
 */
final class RejectionEmailTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    private EventModel $event;

    protected function setUp(): void
    {
        parent::setUp();

        // Away from the nights the clocks change (RegistrationAcrossDaylightSavingTest)
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00'));
        $this->event = EventModel::factory()->create([
            'start_date' => now()->addWeek(),
            'end_date' => now()->addWeek()->addHours(4),
        ]);
        EventRegistrationConfigModel::create(['event_id' => $this->event->id, 'registration_enabled' => true]);
    }

    public function test_rejecting_a_registration_announces_it_once(): void
    {
        Event::fake([RegistrationRejected::class]);
        $registration = $this->registration(RegistrationState::Pending);

        $this->service()->reject((string) $registration->id);

        Event::assertDispatchedTimes(RegistrationRejected::class, 1);
        Event::assertDispatched(
            RegistrationRejected::class,
            fn (RegistrationRejected $event): bool => $event->registrationId === $registration->id
                && $event->eventId === $this->event->id
                && $event->userId === $registration->user_id,
        );
    }

    public function test_rejecting_an_already_rejected_registration_announces_nothing(): void
    {
        Event::fake([RegistrationRejected::class]);
        $registration = $this->registration(RegistrationState::Rejected);

        $this->service()->reject((string) $registration->id);

        Event::assertNotDispatched(RegistrationRejected::class);
    }

    public function test_rejecting_many_announces_each_registration_that_was_rejected(): void
    {
        Event::fake([RegistrationRejected::class]);
        $pending = $this->registration(RegistrationState::Pending);
        $confirmed = $this->registration(RegistrationState::Confirmed);
        $alreadyRejected = $this->registration(RegistrationState::Rejected);

        $this->service()->rejectMany([(string) $pending->id, (string) $confirmed->id, (string) $alreadyRejected->id]);

        Event::assertDispatchedTimes(RegistrationRejected::class, 2);
        foreach ([$pending, $confirmed] as $registration) {
            Event::assertDispatched(RegistrationRejected::class, fn (RegistrationRejected $event): bool => $event->registrationId === $registration->id);
        }
    }

    public function test_a_rejection_rolled_back_announces_nothing(): void
    {
        Event::fake([RegistrationRejected::class]);
        $registration = $this->registration(RegistrationState::Pending);

        try {
            DB::transaction(function () use ($registration): void {
                $this->service()->reject((string) $registration->id);

                throw new RuntimeException('rolled back');
            });
        } catch (RuntimeException) {
            // The rejection never happened
        }

        Event::assertNotDispatched(RegistrationRejected::class);
        $this->assertSame(RegistrationState::Pending, $registration->fresh()?->state);
    }

    public function test_the_reject_action_emails_the_person_once(): void
    {
        Notification::fake();
        $this->registerOnAdminPanel();
        $this->actingAsPanelAdmin();
        $registration = $this->registration(RegistrationState::Pending);

        $this->table()->callTableAction('reject', $registration)->assertHasNoErrors();

        Notification::assertSentToTimes($this->user($registration), RegistrationRejectedNotification::class, 1);
    }

    public function test_the_reject_selected_action_emails_each_person_once(): void
    {
        Notification::fake();
        $this->registerOnAdminPanel();
        $this->actingAsPanelAdmin();
        $pending = $this->registration(RegistrationState::Pending);
        $waiting = $this->registration(RegistrationState::WaitingList, 1);
        $alreadyRejected = $this->registration(RegistrationState::Rejected);

        $this->table()->callTableBulkAction('reject_selected', [$pending, $waiting, $alreadyRejected])->assertHasNoErrors();

        Notification::assertSentToTimes($this->user($pending), RegistrationRejectedNotification::class, 1);
        Notification::assertSentToTimes($this->user($waiting), RegistrationRejectedNotification::class, 1);
        Notification::assertNotSentTo($this->user($alreadyRejected), RegistrationRejectedNotification::class);
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

    private function user(EventRegistrationModel $registration): UserModel
    {
        return UserModel::query()->findOrFail($registration->user_id);
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
