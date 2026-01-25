<?php

declare(strict_types=1);

namespace Modules\EventRegistrations;

use App\Application\Modules\DTOs\PermissionDTO;
use App\Application\Modules\DTOs\SlotRegistrationDTO;
use App\Filament\Resources\EventResource;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Modules\ModuleServiceProvider;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Illuminate\Support\Facades\Event;
use Inertia\Inertia;
use Livewire\Livewire;
use Modules\EventRegistrations\Application\Services\EventRegistrationServiceInterface;
use Modules\EventRegistrations\Application\Services\RegistrationNotificationServiceInterface;
use Modules\EventRegistrations\Application\Services\RegistrationQueryServiceInterface;
use Modules\EventRegistrations\Application\Services\WaitingListServiceInterface;
use Modules\EventRegistrations\Domain\Events\RegistrationConfirmed;
use Modules\EventRegistrations\Domain\Events\RegistrationRejected;
use Modules\EventRegistrations\Domain\Events\UserRegisteredToEvent;
use Modules\EventRegistrations\Domain\Events\UserUnregisteredFromEvent;
use Modules\EventRegistrations\Domain\Events\WaitingListPromoted;
use Modules\EventRegistrations\Domain\Repositories\EventRegistrationConfigRepositoryInterface;
use Modules\EventRegistrations\Domain\Repositories\EventRegistrationRepositoryInterface;
use Modules\EventRegistrations\Filament\Pages\EventRegistrationConfigPage;
use Modules\EventRegistrations\Filament\RelationManagers\RegistrationConfigRelationManager;
use Modules\EventRegistrations\Filament\RelationManagers\RegistrationsRelationManager;
use Modules\EventRegistrations\Filament\Widgets\RegistrationStatsWidget;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationConfigModel;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationModel;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Repositories\EloquentEventRegistrationConfigRepository;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Repositories\EloquentEventRegistrationRepository;
use Modules\EventRegistrations\Infrastructure\Services\EventRegistrationService;
use Modules\EventRegistrations\Infrastructure\Services\RegistrationNotificationService;
use Modules\EventRegistrations\Infrastructure\Services\RegistrationQueryService;
use Modules\EventRegistrations\Infrastructure\Services\WaitingListService;
use Modules\EventRegistrations\Listeners\PromoteFromWaitingListOnCancellation;
use Modules\EventRegistrations\Listeners\SendRegistrationConfirmedEmail;
use Modules\EventRegistrations\Listeners\SendRegistrationRejectedEmail;
use Modules\EventRegistrations\Listeners\SendUserRegisteredEmail;
use Modules\EventRegistrations\Listeners\SendWaitingListPromotedEmail;
use Modules\EventRegistrations\Policies\EventRegistrationPolicy;

final class EventRegistrationsServiceProvider extends ModuleServiceProvider
{
    public function moduleName(): string
    {
        return 'event-registrations';
    }

    public function register(): void
    {
        parent::register();

        // Register model extensions (macros) early so they're available before boot
        $this->registerModelExtensions();

        // Bind repository interfaces to implementations
        $this->app->bind(
            EventRegistrationRepositoryInterface::class,
            EloquentEventRegistrationRepository::class
        );

        $this->app->bind(
            EventRegistrationConfigRepositoryInterface::class,
            EloquentEventRegistrationConfigRepository::class
        );

        // Bind service interfaces to implementations
        $this->app->bind(
            EventRegistrationServiceInterface::class,
            EventRegistrationService::class
        );

        $this->app->bind(
            RegistrationQueryServiceInterface::class,
            RegistrationQueryService::class
        );

        $this->app->bind(
            WaitingListServiceInterface::class,
            WaitingListService::class
        );

        $this->app->bind(
            RegistrationNotificationServiceInterface::class,
            RegistrationNotificationService::class
        );
    }

    public function boot(): void
    {
        parent::boot();

        // Register Livewire components first (before Filament extensions)
        $this->registerLivewireComponents();
        $this->registerFilamentExtensions();
        $this->registerEventListeners();
        $this->shareInertiaData();
    }

    /**
     * Register dynamic relationships on core models.
     * Uses resolveRelationUsing() which is the correct way to add relationships dynamically.
     * Called early in register() to ensure relationships are available before Filament loads.
     */
    private function registerModelExtensions(): void
    {
        // Add registrations() relationship to EventModel dynamically
        // Note: resolveRelationUsing is idempotent - calling it multiple times is safe
        EventModel::resolveRelationUsing('registrations', function (EventModel $eventModel) {
            return $eventModel->hasMany(EventRegistrationModel::class, 'event_id', 'id');
        });

        // Add registrationConfig() relationship for the configuration form section
        EventModel::resolveRelationUsing('registrationConfig', function (EventModel $eventModel) {
            return $eventModel->hasOne(EventRegistrationConfigModel::class, 'event_id', 'id');
        });
    }

    /**
     * Register Livewire components from the module.
     */
    private function registerLivewireComponents(): void
    {
        if (! class_exists(Livewire::class)) {
            return;
        }

        // Register RelationManagers
        Livewire::component(
            'modules.event-registrations.filament.relation-managers.registrations-relation-manager',
            RegistrationsRelationManager::class
        );

        Livewire::component(
            'modules.event-registrations.filament.relation-managers.registration-config-relation-manager',
            RegistrationConfigRelationManager::class
        );

        // Register Widget
        Livewire::component(
            'modules.event-registrations.filament.widgets.registration-stats-widget',
            RegistrationStatsWidget::class
        );

        // Register Page
        Livewire::component(
            'modules.event-registrations.filament.pages.event-registration-config-page',
            EventRegistrationConfigPage::class
        );
    }

    /**
     * Register Filament extensions: RelationManagers for config and registrations.
     */
    private function registerFilamentExtensions(): void
    {
        if (! class_exists(EventResource::class)) {
            return;
        }

        // Add RelationManagers:
        // 1. Config tab (always visible) - for configuring registration settings
        // 2. Registrations tab (visible when enabled OR has registrations) - for managing registered users
        EventResource::extendRelations([
            RegistrationConfigRelationManager::class,
            RegistrationsRelationManager::class,
        ]);
    }

    /**
     * Register domain event listeners.
     */
    private function registerEventListeners(): void
    {
        Event::listen(
            UserUnregisteredFromEvent::class,
            PromoteFromWaitingListOnCancellation::class
        );

        Event::listen(
            RegistrationConfirmed::class,
            SendRegistrationConfirmedEmail::class
        );

        Event::listen(
            WaitingListPromoted::class,
            SendWaitingListPromotedEmail::class
        );

        Event::listen(
            UserRegisteredToEvent::class,
            SendUserRegisteredEmail::class
        );

        Event::listen(
            RegistrationRejected::class,
            SendRegistrationRejectedEmail::class
        );
    }

    /**
     * Share registration data with Inertia for the frontend.
     */
    private function shareInertiaData(): void
    {
        if (! class_exists(Inertia::class)) {
            return;
        }

        // Registration data is shared per-event via the controller
        // Global settings can be shared here if needed
    }

    /**
     * @return array<class-string, class-string>
     */
    public function registerPolicies(): array
    {
        return [
            EventRegistrationModel::class => EventRegistrationPolicy::class,
        ];
    }

    /**
     * @return array<PermissionDTO>
     */
    public function registerPermissions(): array
    {
        return [
            new PermissionDTO(
                name: 'registrations.view_any',
                label: __('event-registrations::messages.permissions.view_any'),
                group: __('event-registrations::messages.navigation'),
                module: 'event-registrations',
                roles: ['editor'],
            ),
            new PermissionDTO(
                name: 'registrations.view',
                label: __('event-registrations::messages.permissions.view'),
                group: __('event-registrations::messages.navigation'),
                module: 'event-registrations',
                roles: ['editor'],
            ),
            new PermissionDTO(
                name: 'registrations.create',
                label: __('event-registrations::messages.permissions.create'),
                group: __('event-registrations::messages.navigation'),
                module: 'event-registrations',
                roles: ['editor'],
            ),
            new PermissionDTO(
                name: 'registrations.update',
                label: __('event-registrations::messages.permissions.update'),
                group: __('event-registrations::messages.navigation'),
                module: 'event-registrations',
                roles: ['editor'],
            ),
            new PermissionDTO(
                name: 'registrations.delete',
                label: __('event-registrations::messages.permissions.delete'),
                group: __('event-registrations::messages.navigation'),
                module: 'event-registrations',
                roles: [],
            ),
            new PermissionDTO(
                name: 'registrations.manage_config',
                label: __('event-registrations::messages.permissions.manage_config'),
                group: __('event-registrations::messages.navigation'),
                module: 'event-registrations',
                roles: ['editor'],
            ),
            new PermissionDTO(
                name: 'registrations.export',
                label: __('event-registrations::messages.permissions.export'),
                group: __('event-registrations::messages.navigation'),
                module: 'event-registrations',
                roles: ['editor'],
            ),
        ];
    }

    /**
     * @return array<string, array{icon?: string, sort?: int}>
     */
    public function registerNavigationGroups(): array
    {
        // Uses existing 'Contenido' navigation group from events
        return [];
    }

    /**
     * Register slot components for the frontend.
     * Displays the registration button on the event detail page.
     *
     * @return array<SlotRegistrationDTO>
     */
    public function registerSlots(): array
    {
        return [
            new SlotRegistrationDTO(
                slot: 'event-detail-actions',
                component: 'components/RegistrationButton.vue',
                module: $this->moduleName(),
                order: 0,
                props: [],
                dataKeys: ['event'],
            ),
        ];
    }

    /**
     * @return array<\Filament\Forms\Components\Component>
     */
    public function getSettingsSchema(): array
    {
        return [
            Section::make(__('event-registrations::messages.settings.general'))
                ->schema([
                    Toggle::make('default_registration_enabled')
                        ->label(__('event-registrations::messages.settings.default_registration_enabled'))
                        ->helperText(__('event-registrations::messages.settings.default_registration_enabled_help'))
                        ->default(false),
                    Toggle::make('default_waiting_list_enabled')
                        ->label(__('event-registrations::messages.settings.default_waiting_list'))
                        ->default(true),
                    Toggle::make('default_requires_confirmation')
                        ->label(__('event-registrations::messages.settings.default_requires_confirmation'))
                        ->default(false),
                    TextInput::make('default_max_participants')
                        ->label(__('event-registrations::messages.settings.default_max_participants'))
                        ->numeric()
                        ->nullable(),
                    TextInput::make('default_max_waiting_list')
                        ->label(__('event-registrations::messages.settings.default_max_waiting_list'))
                        ->numeric()
                        ->nullable(),
                ]),
            Section::make(__('event-registrations::messages.settings.emails'))
                ->schema([
                    Toggle::make('send_registration_email')
                        ->label(__('event-registrations::messages.settings.send_registration_email'))
                        ->default(true),
                    Toggle::make('send_confirmation_email')
                        ->label(__('event-registrations::messages.settings.send_confirmation_email'))
                        ->default(true),
                    Toggle::make('send_waiting_list_email')
                        ->label(__('event-registrations::messages.settings.send_waiting_list_email'))
                        ->default(true),
                    Toggle::make('send_promotion_email')
                        ->label(__('event-registrations::messages.settings.send_promotion_email'))
                        ->default(true),
                    Toggle::make('send_cancellation_email')
                        ->label(__('event-registrations::messages.settings.send_cancellation_email'))
                        ->default(true),
                    Toggle::make('send_rejection_email')
                        ->label(__('event-registrations::messages.settings.send_rejection_email'))
                        ->default(true),
                ]),
        ];
    }
}
