<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Feature\Filament;

use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Livewire\Livewire;
use Modules\EventRegistrations\Filament\Pages\EventRegistrationConfigPage;
use Modules\EventRegistrations\Filament\RelationManagers\RegistrationConfigRelationManager;
use Modules\EventRegistrations\Filament\RelationManagers\RegistrationsRelationManager;
use Modules\EventRegistrations\Filament\Widgets\RegistrationStatsWidget;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationConfigModel;
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
}
