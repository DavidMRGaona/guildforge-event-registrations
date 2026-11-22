<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Feature\Filament;

use App\Application\Activity\Services\ActivityPublisherInterface;
use App\Domain\Activity\Events\ActivityRecorded;
use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Filament\RelationManagers\RegistrationsRelationManager;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationModel;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Deleting confirmed registrations of an event that already ended, one by one or in bulk from the
 * panel's tab, is published for the activity contract: the bulk action deletes record by record,
 * so the models announce each deletion.
 */
final class StaffDeletesPublishActivityTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00'));
        $this->registerOnAdminPanel();
        $this->actingAsPanelAdmin();
    }

    public function test_deleting_selected_confirmed_registrations_of_an_ended_event_is_published(): void
    {
        $event = $this->endedEvent();
        $registrations = [$this->confirmed($event), $this->confirmed($event)];
        $this->listen();

        $this->table($event)->callTableBulkAction('delete', $registrations)->assertHasNoErrors();

        $this->assertSame(0, EventRegistrationModel::query()->count());
        Event::assertDispatched(ActivityRecorded::class, fn (ActivityRecorded $recorded): bool => $recorded->subjectId === $event->id);
    }

    public function test_deleting_one_confirmed_registration_of_an_ended_event_is_published(): void
    {
        $event = $this->endedEvent();
        $registration = $this->confirmed($event);
        $this->listen();

        $this->table($event)->callTableAction('delete', $registration)->assertHasNoErrors();

        Event::assertDispatched(ActivityRecorded::class, fn (ActivityRecorded $recorded): bool => $recorded->subjectId === $event->id);
    }

    private function listen(): void
    {
        Event::fake([ActivityRecorded::class]);
        $this->app->forgetInstance(ActivityPublisherInterface::class);
    }

    /**
     * @return Testable<RegistrationsRelationManager>
     */
    private function table(EventModel $event): Testable
    {
        return Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $event, 'pageClass' => EditEvent::class]);
    }

    private function endedEvent(): EventModel
    {
        return EventModel::factory()->published()->create([
            'start_date' => now()->subDays(3),
            'end_date' => now()->subDays(3)->addHours(4),
        ]);
    }

    private function confirmed(EventModel $event): EventRegistrationModel
    {
        return EventRegistrationModel::create([
            'event_id' => $event->id,
            'user_id' => UserModel::factory()->create()->id,
            'state' => RegistrationState::Confirmed,
            'confirmed_at' => now(),
        ]);
    }
}
