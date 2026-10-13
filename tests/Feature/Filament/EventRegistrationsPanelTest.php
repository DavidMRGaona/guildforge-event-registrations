<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Feature\Filament;

use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Livewire\Livewire;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\EventRegistrationsServiceProvider;
use Modules\EventRegistrations\Filament\Pages\EventRegistrationConfigPage;
use Modules\EventRegistrations\Filament\RelationManagers\RegistrationConfigRelationManager;
use Modules\EventRegistrations\Filament\RelationManagers\RegistrationsRelationManager;
use Modules\EventRegistrations\Filament\Widgets\RegistrationStatsWidget;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationConfigModel;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationModel;
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

        $csv = base64_decode((string) data_get($component->effects, 'download.content'));

        $this->assertStringStartsWith("\u{FEFF}", $csv);
        $this->assertStringContainsString(__('event-registrations::messages.fields.email'), $csv);
        $this->assertStringContainsString('Ana Pérez', $csv);
        $this->assertStringContainsString('ana@example.com', $csv);
        $this->assertStringContainsString(RegistrationState::Confirmed->label(), $csv);
        $this->assertStringNotContainsString('outsider@example.com', $csv);
    }

    public function test_export_neutralises_values_that_spreadsheets_would_run_as_formulas(): void
    {
        EventRegistrationConfigModel::create(['event_id' => $this->event->id, 'registration_enabled' => true]);
        $attendee = UserModel::factory()->create(['name' => '=HYPERLINK("http://x")', 'email' => 'formula@example.com']);
        EventRegistrationModel::create([
            'event_id' => $this->event->id,
            'user_id' => $attendee->id,
            'state' => RegistrationState::Confirmed,
        ]);

        $component = Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $this->event, 'pageClass' => EditEvent::class])
            ->callTableAction('export');

        $csv = base64_decode((string) data_get($component->effects, 'download.content'));
        $rows = array_map(fn (string $line): array => str_getcsv($line, escape: ''), array_values(array_filter(explode("\n", $csv))));

        $this->assertSame('\'=HYPERLINK("http://x")', $rows[1][0]);
        $this->assertSame('formula@example.com', $rows[1][1]);
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
}
