<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Feature\Filament;

use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Filament\Pages\EventRegistrationConfigPage;
use Modules\EventRegistrations\Filament\RelationManagers\RegistrationConfigRelationManager;
use Modules\EventRegistrations\Filament\RelationManagers\RegistrationsRelationManager;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationConfigModel;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationModel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The registration tabs of the event edit page follow the module's own permissions, not the
 * permission to edit the event.
 */
final class RegistrationTabsPermissionsTest extends ModuleTestCase
{
    use CreatesUsersWithPermissions;
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    private const string VIEW_ANY = 'event-registrations:registrations.view_any';

    private const string UPDATE = 'event-registrations:registrations.update';

    private const string MANAGE_CONFIG = 'event-registrations:registrations.manage_config';

    private EventModel $event;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00'));
        $this->registerOnAdminPanel();
        $this->event = EventModel::factory()->create(['start_date' => now()->addMonth(), 'end_date' => now()->addMonth()->addHours(4)]);
        EventRegistrationConfigModel::create(['event_id' => $this->event->id, 'registration_enabled' => true]);
    }

    public function test_editing_the_event_alone_shows_neither_registration_tab(): void
    {
        $this->registration(RegistrationState::Pending);
        $this->actingAs($this->editorWithPermissions(['events.update']));

        $this->assertFalse(RegistrationsRelationManager::canViewForRecord($this->event, EditEvent::class));
        $this->assertFalse(RegistrationConfigRelationManager::canViewForRecord($this->event, EditEvent::class));
    }

    public function test_viewing_registrations_shows_the_registrations_tab_only(): void
    {
        $this->actingAs($this->editorWithPermissions(['events.update', self::VIEW_ANY]));

        $this->assertTrue(RegistrationsRelationManager::canViewForRecord($this->event, EditEvent::class));
        $this->assertFalse(RegistrationConfigRelationManager::canViewForRecord($this->event, EditEvent::class));
    }

    public function test_managing_the_configuration_shows_the_configuration_tab_only(): void
    {
        $this->registration(RegistrationState::Pending);
        $this->actingAs($this->editorWithPermissions(['events.update', self::MANAGE_CONFIG]));

        $this->assertTrue(RegistrationConfigRelationManager::canViewForRecord($this->event, EditEvent::class));
        $this->assertFalse(RegistrationsRelationManager::canViewForRecord($this->event, EditEvent::class));
    }

    public function test_the_registrations_tab_refuses_to_load_without_permission_to_view_registrations(): void
    {
        $this->registration(RegistrationState::Pending);
        $this->actingAs($this->editorWithPermissions(['events.update']));

        Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $this->event, 'pageClass' => EditEvent::class])
            ->assertForbidden();
    }

    public function test_the_configuration_tab_refuses_to_load_without_permission_to_manage_the_configuration(): void
    {
        $this->actingAs($this->editorWithPermissions(['events.update']));

        Livewire::test(RegistrationConfigRelationManager::class, ['ownerRecord' => $this->event, 'pageClass' => EditEvent::class])
            ->assertForbidden();
    }

    public function test_the_configuration_page_refuses_to_load_without_permission_to_manage_the_configuration(): void
    {
        $this->actingAs($this->editorWithPermissions(['events.update']));

        Livewire::test(EventRegistrationConfigPage::class, ['eventId' => $this->event->id])
            ->assertForbidden();
    }

    #[DataProvider('stateChangingActions')]
    public function test_a_state_change_needs_permission_to_update_registrations(string $action, RegistrationState $from, RegistrationState $to): void
    {
        $registration = $this->registration($from);
        $this->actingAs($this->editorWithPermissions(['events.update', self::VIEW_ANY]));

        $table = $this->table()->assertTableActionHidden($action, $registration);
        // Straight to Livewire, as a request can do without the button on screen
        $table->mountAction(TestAction::make($action)->table($registration))
            ->call('callMountedAction');

        $this->assertNotSame($to, $registration->fresh()?->state);
        $this->assertSame($from, $registration->fresh()?->state);
    }

    #[DataProvider('stateChangingBulkActions')]
    public function test_a_bulk_state_change_needs_permission_to_update_registrations(string $action, RegistrationState $from, RegistrationState $to): void
    {
        $registration = $this->registration($from);
        $this->actingAs($this->editorWithPermissions(['events.update', self::VIEW_ANY]));

        $table = $this->table()->assertTableBulkActionHidden($action);
        $table->selectTableRecords([$registration])
            ->mountAction(TestAction::make($action)->table()->bulk())
            ->call('callMountedAction');

        $this->assertNotSame($to, $registration->fresh()?->state);
        $this->assertSame($from, $registration->fresh()?->state);
    }

    #[DataProvider('stateChangingActions')]
    public function test_a_state_change_stops_once_the_permission_is_gone(string $action, RegistrationState $from, RegistrationState $to): void
    {
        $registration = $this->registration($from);
        $this->actingAs($this->editorWithPermissions(['events.update', self::VIEW_ANY, self::UPDATE]));
        $table = $this->table()->mountAction(TestAction::make($action)->table($registration));

        // The confirmation modal was opened with the permission; the next request comes without it
        $this->actingAs($this->editorWithPermissions(['events.update', self::VIEW_ANY]));
        $table->call('callMountedAction');

        $this->assertNotSame($to, $registration->fresh()?->state);
        $this->assertSame($from, $registration->fresh()?->state);
    }

    #[DataProvider('stateChangingActions')]
    public function test_a_state_change_works_with_permission_to_update_registrations(string $action, RegistrationState $from, RegistrationState $to): void
    {
        $registration = $this->registration($from);
        $this->actingAs($this->editorWithPermissions(['events.update', self::VIEW_ANY, self::UPDATE]));

        $this->table()->callTableAction($action, $registration)->assertHasNoErrors();

        $this->assertSame($to, $registration->fresh()?->state);
    }

    #[DataProvider('stateChangingBulkActions')]
    public function test_a_bulk_state_change_works_with_permission_to_update_registrations(string $action, RegistrationState $from, RegistrationState $to): void
    {
        $registration = $this->registration($from);
        $this->actingAs($this->editorWithPermissions(['events.update', self::VIEW_ANY, self::UPDATE]));

        $this->table()->callTableBulkAction($action, [$registration])->assertHasNoErrors();

        $this->assertSame($to, $registration->fresh()?->state);
    }

    /**
     * @return array<string, array{string, RegistrationState, RegistrationState}>
     */
    public static function stateChangingActions(): array
    {
        return [
            'confirm' => ['confirm', RegistrationState::Pending, RegistrationState::Confirmed],
            'reject' => ['reject', RegistrationState::Pending, RegistrationState::Rejected],
            'move to waiting list' => ['move_to_waiting', RegistrationState::Confirmed, RegistrationState::WaitingList],
        ];
    }

    /**
     * @return array<string, array{string, RegistrationState, RegistrationState}>
     */
    public static function stateChangingBulkActions(): array
    {
        return [
            'confirm selected' => ['confirm_selected', RegistrationState::Pending, RegistrationState::Confirmed],
            'reject selected' => ['reject_selected', RegistrationState::Pending, RegistrationState::Rejected],
        ];
    }

    public function test_saving_the_configuration_tab_needs_permission_to_manage_the_configuration(): void
    {
        $this->actingAs($this->editorWithPermissions(['events.update', self::MANAGE_CONFIG]));
        $tab = Livewire::test(RegistrationConfigRelationManager::class, ['ownerRecord' => $this->event, 'pageClass' => EditEvent::class])
            ->set('configData.registration_enabled', false);

        $this->actingAs($this->editorWithPermissions(['events.update']));
        $tab->call('save')
            ->assertForbidden();

        $this->assertTrue(EventRegistrationConfigModel::query()->where('event_id', $this->event->id)->firstOrFail()->registration_enabled);
    }

    public function test_saving_the_configuration_page_needs_permission_to_manage_the_configuration(): void
    {
        $this->actingAs($this->editorWithPermissions(['events.update', self::MANAGE_CONFIG]));
        $page = Livewire::test(EventRegistrationConfigPage::class, ['eventId' => $this->event->id])
            ->set('data.registration_enabled', false);

        $this->actingAs($this->editorWithPermissions(['events.update']));
        $page->call('save')
            ->assertForbidden();

        $this->assertTrue(EventRegistrationConfigModel::query()->where('event_id', $this->event->id)->firstOrFail()->registration_enabled);
    }

    public function test_the_configuration_tab_saves_with_permission_to_manage_the_configuration(): void
    {
        $this->actingAs($this->editorWithPermissions(['events.update', self::MANAGE_CONFIG]));

        Livewire::test(RegistrationConfigRelationManager::class, ['ownerRecord' => $this->event, 'pageClass' => EditEvent::class])
            ->set('configData.max_participants', 12)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(12, EventRegistrationConfigModel::query()->where('event_id', $this->event->id)->firstOrFail()->max_participants);
    }

    /**
     * @return Testable<RegistrationsRelationManager>
     */
    private function table(): Testable
    {
        return Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $this->event, 'pageClass' => EditEvent::class]);
    }

    private function registration(RegistrationState $state): EventRegistrationModel
    {
        return EventRegistrationModel::create([
            'event_id' => $this->event->id,
            'user_id' => UserModel::factory()->create()->id,
            'state' => $state,
            'position' => $state === RegistrationState::WaitingList ? 1 : null,
        ]);
    }
}
