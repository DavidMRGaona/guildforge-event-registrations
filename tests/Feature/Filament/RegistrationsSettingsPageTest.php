<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Feature\Filament;

use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Application\Modules\Services\ModuleSettingsStoreInterface;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Filament\Clusters\SettingsCluster;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Livewire\Livewire;
use Modules\EventRegistrations\EventRegistrationsServiceProvider;
use Modules\EventRegistrations\Filament\Pages\RegistrationsSettings;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The module's defaults and emails are edited in the «Configuración» cluster with a permission
 * of the module, not from «Módulos» with the technical one (core#666).
 */
final class RegistrationsSettingsPageTest extends ModuleTestCase
{
    use CreatesUsersWithPermissions;
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    private const string MANAGE_SETTINGS = 'event-registrations:registrations.manage_settings';

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([], [RegistrationsSettings::class]);
    }

    public function test_the_provider_declares_the_page_and_lists_it(): void
    {
        $provider = new EventRegistrationsServiceProvider($this->app);

        $this->assertSame(RegistrationsSettings::class, $provider->getSettingsPage());
        $this->assertContains(RegistrationsSettings::class, $provider->registerFilamentPages());
    }

    public function test_the_generic_schema_is_empty(): void
    {
        $this->assertSame([], (new EventRegistrationsServiceProvider($this->app))->getSettingsSchema());
    }

    public function test_the_page_sits_in_the_settings_cluster_with_its_slot_and_a_short_label(): void
    {
        $this->assertSame(SettingsCluster::class, RegistrationsSettings::getCluster());
        $this->assertSame(127, RegistrationsSettings::getNavigationSort());
        $this->assertSame('Inscripciones', RegistrationsSettings::getNavigationLabel());
    }

    public function test_the_module_permission_is_declared_and_never_granted_by_default(): void
    {
        $permissions = collect((new EventRegistrationsServiceProvider($this->app))->registerPermissions())
            ->keyBy(fn ($permission): string => $permission->name);

        $this->assertTrue($permissions->has('registrations.manage_settings'));
        $this->assertSame([], $permissions['registrations.manage_settings']->roles);
        $this->assertFalse($permissions['registrations.manage_settings']->adminOnly);
    }

    public function test_the_module_permission_opens_the_page(): void
    {
        $this->actingAs($this->editorWithPermissions([self::MANAGE_SETTINGS]));

        $this->assertTrue(RegistrationsSettings::canAccess());
    }

    public function test_the_technical_modules_permission_alone_is_not_enough(): void
    {
        $this->actingAs($this->editorWithPermissions(['modules.configure']));

        $this->assertFalse(RegistrationsSettings::canAccess());
    }

    public function test_an_administrator_can_open_it(): void
    {
        $this->actingAsPanelAdmin();

        $this->assertTrue(RegistrationsSettings::canAccess());
    }

    public function test_the_page_loads_the_values_saved_before_the_move(): void
    {
        $this->seedStoredSettings(['default_registration_enabled' => true, 'default_max_participants' => 12, 'reminder_hours_before' => 48, 'send_cancellation_email' => false]);
        $this->actingAs($this->editorWithPermissions([self::MANAGE_SETTINGS]));

        Livewire::test(RegistrationsSettings::class)
            ->assertSet('data.default_registration_enabled', true)
            ->assertSet('data.default_max_participants', 12)
            ->assertSet('data.reminder_hours_before', 48)
            ->assertSet('data.send_cancellation_email', false);
    }

    public function test_saving_writes_to_the_same_module_settings_row(): void
    {
        $this->actingAs($this->editorWithPermissions([self::MANAGE_SETTINGS]));

        Livewire::test(RegistrationsSettings::class)
            ->fillForm(['default_waiting_list_enabled' => false, 'reminder_hours_before' => 6])
            ->call('save')
            ->assertHasNoFormErrors();

        $stored = app(ModuleManagerServiceInterface::class)->getSettings(new ModuleName('event-registrations'));
        $this->assertFalse($stored['default_waiting_list_enabled']);
        $this->assertSame(6, (int) $stored['reminder_hours_before']);
        $this->assertDatabaseHas('settings', ['key' => 'module_settings:event-registrations']);
    }

    public function test_saving_without_the_permission_is_refused_and_stores_nothing(): void
    {
        $this->seedStoredSettings(['reminder_hours_before' => 48]);
        $this->actingAs($this->editorWithPermissions([self::MANAGE_SETTINGS]));
        $component = Livewire::test(RegistrationsSettings::class);
        // The permission is taken away after the page was open: Livewire methods are still callable
        $this->actingAs($this->editorWithPermissions(['modules.configure']));

        try {
            $component
                ->set('data.reminder_hours_before', 2)
                ->call('save');
            $this->fail('Saving without the module permission must be refused.');
        } catch (HttpException|HttpResponseException|AuthorizationException) {
            $this->addToAssertionCount(1);
        }

        $stored = app(ModuleManagerServiceInterface::class)->getSettings(new ModuleName('event-registrations'));
        $this->assertSame(48, (int) $stored['reminder_hours_before']);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function seedStoredSettings(array $values): void
    {
        app(ModuleSettingsStoreInterface::class)->store('event-registrations', $values);
        // What the module manager reads at boot
        config()->set('modules.settings.event-registrations', $values);
    }
}
