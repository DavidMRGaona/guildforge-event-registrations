<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Feature\Knowledge;

use App\Application\Knowledge\Services\KnowledgeQueryServiceInterface;
use App\Domain\Knowledge\Enums\GuideAudience;
use App\Domain\Knowledge\ValueObjects\GuideId;
use App\Filament\Pages\KnowledgeBasePage;
use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Livewire\Livewire;
use Modules\EventRegistrations\Filament\RelationManagers\RegistrationConfigRelationManager;
use Modules\EventRegistrations\Filament\RelationManagers\RegistrationsRelationManager;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationConfigModel;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Who sees each of the module's guides, and the help buttons on the event's registration tabs.
 */
final class EventRegistrationsGuidesTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    /** For every account: registering to events and following the registrations. */
    private const array SITE_GUIDES = [
        'event-registrations.cancel',
        'event-registrations.my-registrations',
        'event-registrations.overview',
        'event-registrations.sign-up',
    ];

    private const array PANEL_GUIDES = [
        'event-registrations.panel.configure',
        'event-registrations.panel.manage-registrations',
        'event-registrations.panel.overview',
        'event-registrations.panel.settings',
    ];

    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    private EventModel $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel();
        $this->event = EventModel::factory()->create();
        $this->artisanCommand('knowledge:sync')->assertSuccessful()->run();
    }

    public function test_every_account_sees_the_site_guides(): void
    {
        $this->assertSame(self::SITE_GUIDES, $this->visibleGuides(UserModel::factory()->create(), GuideAudience::Site));
    }

    public function test_each_permission_shows_only_its_panel_guides(): void
    {
        $this->assertSame([
            'event-registrations.panel.configure',
            'event-registrations.panel.overview',
        ], $this->visibleGuides($this->userWith('events.update', 'event-registrations:registrations.manage_config'), GuideAudience::Panel));

        $this->assertSame([
            'event-registrations.panel.manage-registrations',
            'event-registrations.panel.overview',
        ], $this->visibleGuides($this->userWith('events.update', 'event-registrations:registrations.view_any'), GuideAudience::Panel));

        $this->assertSame(['event-registrations.panel.settings'], $this->visibleGuides($this->userWith('modules.configure'), GuideAudience::Panel));

        $this->assertSame([], $this->visibleGuides(UserModel::factory()->editor()->create(), GuideAudience::Panel));
    }

    public function test_every_guide_renders_without_raw_markup(): void
    {
        $admin = UserModel::factory()->admin()->create();

        $this->assertSame(self::PANEL_GUIDES, $this->visibleGuides($admin, GuideAudience::Panel));

        $guides = [
            ...array_map(static fn (string $id): array => [GuideAudience::Site, $id], self::SITE_GUIDES),
            ...array_map(static fn (string $id): array => [GuideAudience::Panel, $id], self::PANEL_GUIDES),
        ];

        foreach ($guides as [$audience, $id]) {
            $guide = $this->queries()->guide($admin, $audience, GuideId::fromString($id));
            $this->assertNotNull($guide, $id);
            $this->assertStringNotContainsString('[[', $guide->html, $id);
            $this->assertStringNotContainsString('{{', $guide->html, $id);
            $this->assertStringNotContainsString('[!', $guide->html, $id);
        }
    }

    public function test_the_registrations_tab_opens_its_guide(): void
    {
        $this->actingAsPanelAdmin();
        EventRegistrationConfigModel::create(['event_id' => $this->event->id, 'registration_enabled' => true]);

        Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $this->event, 'pageClass' => EditEvent::class])
            ->assertTableActionVisible('contextualHelp')
            ->assertTableActionHasUrl('contextualHelp', $this->guideUrl('event-registrations.panel.manage-registrations'));
    }

    public function test_the_registrations_tab_has_no_help_for_someone_who_cannot_see_its_guide(): void
    {
        $this->actingAs(UserModel::factory()->editor()->create());
        EventRegistrationConfigModel::create(['event_id' => $this->event->id, 'registration_enabled' => true]);

        Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $this->event, 'pageClass' => EditEvent::class])
            ->assertTableActionDoesNotExist('contextualHelp');
    }

    public function test_the_configuration_tab_opens_its_guide(): void
    {
        $this->actingAsPanelAdmin();

        Livewire::test(RegistrationConfigRelationManager::class, ['ownerRecord' => $this->event, 'pageClass' => EditEvent::class])
            ->assertOk()
            ->assertSeeHtml('data-panel-guide-url="'.e($this->guideUrl('event-registrations.panel.configure')).'"');
    }

    public function test_the_configuration_tab_has_no_help_for_someone_who_cannot_see_its_guide(): void
    {
        $this->actingAs(UserModel::factory()->editor()->create());

        Livewire::test(RegistrationConfigRelationManager::class, ['ownerRecord' => $this->event, 'pageClass' => EditEvent::class])
            ->assertOk()
            ->assertDontSeeHtml('data-panel-guide-url');
    }

    private function guideUrl(string $guideId): string
    {
        return KnowledgeBasePage::getUrl(['guia' => $guideId]);
    }

    /**
     * @return list<string>
     */
    private function visibleGuides(UserModel $user, GuideAudience $audience): array
    {
        $ids = array_values(array_filter(
            $this->queries()->visibleIds($user, $audience),
            static fn (string $id): bool => str_starts_with($id, 'event-registrations.'),
        ));
        sort($ids);

        return $ids;
    }

    private function userWith(string ...$permissionKeys): UserModel
    {
        $role = RoleModel::query()->create(['name' => 'tester_'.uniqid(), 'display_name' => 'Permissions tester']);

        foreach ($permissionKeys as $key) {
            $permission = PermissionModel::query()->firstOrCreate(
                ['key' => $key],
                ['label' => $key, 'resource' => 'registrations', 'action' => 'test', 'module' => 'event-registrations'],
            );
            $role->permissions()->attach($permission->id);
        }

        $user = UserModel::factory()->editor()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    private function queries(): KnowledgeQueryServiceInterface
    {
        return $this->app->make(KnowledgeQueryServiceInterface::class);
    }
}
