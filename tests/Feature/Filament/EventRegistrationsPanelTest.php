<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Feature\Filament;

use App\Filament\Pages\ModuleSettingsPage;
use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\EventRegistrationsServiceProvider;
use Modules\EventRegistrations\Filament\Pages\EventRegistrationConfigPage;
use Modules\EventRegistrations\Filament\RelationManagers\RegistrationConfigRelationManager;
use Modules\EventRegistrations\Filament\RelationManagers\RegistrationsRelationManager;
use Modules\EventRegistrations\Filament\Widgets\RegistrationStatsWidget;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationConfigModel;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationModel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

final class EventRegistrationsPanelTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    private EventModel $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel();
        $this->actingAsPanelAdmin();
        $this->event = EventModel::factory()->create();
    }

    public function test_config_relation_manager_renders(): void
    {
        Livewire::test(RegistrationConfigRelationManager::class, ['ownerRecord' => $this->event, 'pageClass' => EditEvent::class])
            ->assertOk();
    }

    public function test_config_relation_manager_saves_the_configuration(): void
    {
        Livewire::test(RegistrationConfigRelationManager::class, ['ownerRecord' => $this->event, 'pageClass' => EditEvent::class])
            ->set('configData.registration_enabled', true)
            ->set('configData.max_participants', 12)
            ->call('save')
            ->assertHasNoErrors();

        $config = EventRegistrationConfigModel::query()->where('event_id', $this->event->id)->firstOrFail();
        $this->assertTrue($config->registration_enabled);
        $this->assertSame(12, $config->max_participants);
    }

    public function test_registrations_relation_manager_renders_when_registration_is_enabled(): void
    {
        EventRegistrationConfigModel::create(['event_id' => $this->event->id, 'registration_enabled' => true]);

        Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $this->event, 'pageClass' => EditEvent::class])
            ->assertOk();
    }

    public function test_config_page_renders(): void
    {
        Livewire::test(EventRegistrationConfigPage::class, ['eventId' => $this->event->id])->assertOk();
    }

    #[DataProvider('datesCappedAtTheEventStart')]
    public function test_the_config_tab_refuses_a_date_after_the_event_start(string $field): void
    {
        $event = $this->eventStartingAt('2030-04-12 10:00:00');

        Livewire::test(RegistrationConfigRelationManager::class, ['ownerRecord' => $event, 'pageClass' => EditEvent::class])
            ->set('configData.registration_enabled', true)
            ->set('configData.registration_opens_at', '2030-04-01 10:00:00')
            ->set("configData.{$field}", '2030-04-12 10:01:00')
            ->call('save')
            ->assertHasErrors(["configData.{$field}" => 'before_or_equal'])
            ->assertSee('No puede ser posterior al inicio del evento (12/04/2030 10:00)');

        $this->assertFalse(EventRegistrationConfigModel::query()->where('event_id', $event->id)->exists());
    }

    #[DataProvider('datesCappedAtTheEventStart')]
    public function test_the_config_tab_accepts_a_date_equal_to_the_event_start(string $field): void
    {
        $event = $this->eventStartingAt('2030-04-12 10:00:00');

        Livewire::test(RegistrationConfigRelationManager::class, ['ownerRecord' => $event, 'pageClass' => EditEvent::class])
            ->set('configData.registration_enabled', true)
            ->set('configData.registration_opens_at', '2030-04-01 10:00:00')
            ->set("configData.{$field}", '2030-04-12 10:00:00')
            ->call('save')
            ->assertHasNoErrors();

        $config = EventRegistrationConfigModel::query()->where('event_id', $event->id)->firstOrFail();
        $this->assertSame('2030-04-12 10:00:00', $config->{$field}?->format('Y-m-d H:i:s'));
    }

    #[DataProvider('datesCappedAtTheEventStart')]
    public function test_the_config_page_refuses_a_date_after_the_event_start(string $field): void
    {
        $event = $this->eventStartingAt('2030-04-12 10:00:00');

        Livewire::test(EventRegistrationConfigPage::class, ['eventId' => $event->id])
            ->set('data.registration_opens_at', '2030-04-01 10:00:00')
            ->set("data.{$field}", '2030-04-12 10:01:00')
            ->call('save')
            ->assertHasErrors(["data.{$field}" => 'before_or_equal'])
            ->assertSee('No puede ser posterior al inicio del evento (12/04/2030 10:00)');
    }

    #[DataProvider('datesCappedAtTheEventStart')]
    public function test_the_config_page_accepts_a_date_equal_to_the_event_start(string $field): void
    {
        $event = $this->eventStartingAt('2030-04-12 10:00:00');

        Livewire::test(EventRegistrationConfigPage::class, ['eventId' => $event->id])
            ->set('data.max_waiting_list', null)
            ->set('data.registration_opens_at', '2030-04-01 10:00:00')
            ->set("data.{$field}", '2030-04-12 10:00:00')
            ->call('save')
            ->assertHasNoErrors();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function datesCappedAtTheEventStart(): array
    {
        return [
            'registration close' => ['registration_closes_at'],
            'cancellation deadline' => ['cancellation_deadline'],
        ];
    }

    public function test_stats_widget_renders(): void
    {
        Livewire::test(RegistrationStatsWidget::class)->assertOk();
    }

    public function test_export_downloads_the_event_registrations_as_csv(): void
    {
        EventRegistrationConfigModel::create(['event_id' => $this->event->id, 'registration_enabled' => true]);
        $attendee = UserModel::factory()->create(['name' => 'Ana Pérez', 'email' => 'ana@example.com']);
        EventRegistrationModel::create([
            'event_id' => $this->event->id,
            'user_id' => $attendee->id,
            'state' => RegistrationState::Confirmed,
        ]);
        $otherEvent = EventModel::factory()->create();
        $outsider = UserModel::factory()->create(['email' => 'outsider@example.com']);
        EventRegistrationModel::create([
            'event_id' => $otherEvent->id,
            'user_id' => $outsider->id,
            'state' => RegistrationState::Confirmed,
        ]);

        $component = Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $this->event, 'pageClass' => EditEvent::class])
            ->callTableAction('export')
            ->assertFileDownloaded("inscripciones-{$this->event->slug}.csv");

        $csv = $this->downloadedContent($component);

        $this->assertStringStartsWith("\u{FEFF}", $csv);
        $this->assertStringContainsString(__('event-registrations::messages.fields.email'), $csv);
        $this->assertStringContainsString('Ana Pérez', $csv);
        $this->assertStringContainsString('ana@example.com', $csv);
        $this->assertStringContainsString(RegistrationState::Confirmed->label(), $csv);
        $this->assertStringNotContainsString('outsider@example.com', $csv);
    }

    #[DataProvider('formulaValues')]
    public function test_export_neutralises_values_that_spreadsheets_would_run_as_formulas(string $name): void
    {
        $this->registerAttendee(UserModel::factory()->create(['name' => $name, 'email' => 'formula@example.com']));

        $rows = $this->exportedRows();

        $this->assertSame("'".$name, $rows[1][0]);
        $this->assertSame('formula@example.com', $rows[1][1]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function formulaValues(): array
    {
        return [
            'equals' => ['=HYPERLINK("http://x")'],
            'plus' => ['+1+1'],
            'minus' => ['-1+1'],
            'at' => ['@SUM(1;1)'],
            'tab' => ["\t=1+1"],
            'carriage return' => ["\r=1+1"],
        ];
    }

    public function test_export_uses_semicolons_and_quotes_values_that_contain_them(): void
    {
        $this->registerAttendee(UserModel::factory()->create(['name' => 'Pérez; Ana', 'email' => 'ana@example.com']));

        $csv = $this->exportedCsv();
        $rows = $this->exportedRows();

        $this->assertStringContainsString('"Pérez; Ana";ana@example.com;', $csv);
        $this->assertCount(6, $rows[0]);
        $this->assertSame('Pérez; Ana', $rows[1][0]);
        $this->assertSame('ana@example.com', $rows[1][1]);
    }

    public function test_export_includes_registrations_of_deactivated_users(): void
    {
        $attendee = UserModel::factory()->create(['name' => 'Usuaria desactivada', 'email' => 'gone@example.com']);
        $this->registerAttendee($attendee);
        $attendee->delete();

        $rows = $this->exportedRows();

        $this->assertCount(2, $rows);
        $this->assertSame('Usuaria desactivada', $rows[1][0]);
        $this->assertSame('gone@example.com', $rows[1][1]);
    }

    public function test_export_is_hidden_without_the_export_permission(): void
    {
        EventRegistrationConfigModel::create(['event_id' => $this->event->id, 'registration_enabled' => true]);
        $this->actingAs(UserModel::factory()->create());

        Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $this->event, 'pageClass' => EditEvent::class])
            ->assertTableActionHidden('export');
    }

    public function test_the_stats_widget_is_registered_on_the_dashboard(): void
    {
        $this->assertContains(
            RegistrationStatsWidget::class,
            (new EventRegistrationsServiceProvider($this->app))->registerFilamentWidgets(),
        );
    }

    public function test_the_stats_widget_is_visible_to_users_who_can_list_registrations(): void
    {
        $this->assertTrue(RegistrationStatsWidget::canView());
    }

    public function test_the_stats_widget_is_hidden_without_permission_to_list_registrations(): void
    {
        $this->actingAs(UserModel::factory()->create());

        $this->assertFalse(RegistrationStatsWidget::canView());
    }

    public function test_the_settings_form_offers_only_the_emails_the_module_sends(): void
    {
        Livewire::test(ModuleSettingsPage::class, ['module' => 'event-registrations'])
            ->assertOk()
            ->assertFormFieldExists('send_registration_email')
            ->assertFormFieldExists('send_confirmation_email')
            ->assertFormFieldExists('send_promotion_email')
            ->assertFormFieldDoesNotExist('send_waiting_list_email')
            ->assertFormFieldDoesNotExist('send_cancellation_email')
            ->assertFormFieldDoesNotExist('send_rejection_email');
    }

    public function test_settings_saved_with_the_removed_email_toggles_still_load(): void
    {
        config()->set('modules.settings.event-registrations', [
            'default_registration_enabled' => true,
            'send_promotion_email' => false,
            'send_waiting_list_email' => true,
            'send_cancellation_email' => true,
            'send_rejection_email' => true,
        ]);

        Livewire::test(ModuleSettingsPage::class, ['module' => 'event-registrations'])
            ->assertOk()
            ->assertSet('data.default_registration_enabled', true)
            ->assertSet('data.send_promotion_email', false);
    }

    private function registerAttendee(UserModel $attendee): void
    {
        EventRegistrationConfigModel::query()->firstOrCreate(['event_id' => $this->event->id], ['registration_enabled' => true]);
        EventRegistrationModel::create([
            'event_id' => $this->event->id,
            'user_id' => $attendee->id,
            'state' => RegistrationState::Confirmed,
        ]);
    }

    private function exportedCsv(): string
    {
        $component = Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $this->event, 'pageClass' => EditEvent::class])
            ->callTableAction('export');

        return $this->downloadedContent($component);
    }

    /**
     * The exported file parsed as CSV (`;` separator), without the BOM.
     *
     * @return list<list<string|null>>
     */
    private function exportedRows(): array
    {
        $stream = fopen('php://memory', 'r+b');
        $this->assertNotFalse($stream);
        fwrite($stream, substr($this->exportedCsv(), strlen("\u{FEFF}")));
        rewind($stream);

        $rows = [];
        while (($row = fgetcsv($stream, separator: ';', escape: '')) !== false) {
            $rows[] = $row;
        }
        fclose($stream);

        return $rows;
    }

    /**
     * Content of the file the component sent for download (Livewire keeps it base64 encoded in its effects).
     *
     * @param  Testable<RegistrationsRelationManager>  $component
     */
    private function downloadedContent(Testable $component): string
    {
        $content = data_get($component->__get('effects'), 'download.content');

        return base64_decode(is_string($content) ? $content : '');
    }

    private function eventStartingAt(string $startDate): EventModel
    {
        return EventModel::factory()->create([
            'start_date' => $startDate,
            'end_date' => '2030-04-12 14:00:00',
        ]);
    }
}
