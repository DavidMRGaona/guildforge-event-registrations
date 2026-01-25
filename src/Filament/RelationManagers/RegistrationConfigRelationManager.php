<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Filament\RelationManagers;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Database\Eloquent\Model;
use Modules\EventRegistrations\Application\DTOs\UpdateRegistrationConfigDTO;
use Modules\EventRegistrations\Application\Services\EventRegistrationServiceInterface;
use Modules\EventRegistrations\Domain\Repositories\EventRegistrationConfigRepositoryInterface;

/**
 * @property Form $configForm
 */
final class RegistrationConfigRelationManager extends RelationManager
{
    protected static string $relationship = 'registrationConfig';

    protected static ?string $recordTitleAttribute = 'event_id';

    protected static string $view = 'event-registrations::filament.relation-managers.registration-config-form';

    /** @var array<string, mixed> */
    public array $configData = [];

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('event-registrations::messages.config.title');
    }

    /**
     * Always show the config tab so admins can configure registrations.
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof EventModel;
    }

    public function mount(): void
    {
        parent::mount();

        $eventId = $this->getOwnerRecord()->getKey();
        $configRepository = app(EventRegistrationConfigRepositoryInterface::class);
        $config = $configRepository->findByEventOrDefault($eventId);

        $this->configForm->fill([
            'registration_enabled' => $config->isRegistrationEnabled(),
            'max_participants' => $config->maxParticipants(),
            'waiting_list_enabled' => $config->isWaitingListEnabled(),
            'max_waiting_list' => $config->maxWaitingList(),
            'registration_opens_at' => $config->registrationOpensAt()?->format('Y-m-d H:i:s'),
            'registration_closes_at' => $config->registrationClosesAt()?->format('Y-m-d H:i:s'),
            'cancellation_deadline' => $config->cancellationDeadline()?->format('Y-m-d H:i:s'),
            'requires_confirmation' => $config->requiresConfirmation(),
            'members_only' => $config->isMembersOnly(),
            'notification_email' => $config->notificationEmail(),
        ]);
    }

    /**
     * @return array<string, Form>
     */
    protected function getForms(): array
    {
        return [
            'configForm' => $this->makeForm()
                ->schema($this->getFormSchema())
                ->statePath('configData'),
        ];
    }

    /**
     * @return array<\Filament\Forms\Components\Component>
     */
    protected function getFormSchema(): array
    {
        return [
            Section::make(__('event-registrations::messages.config.general'))
                ->schema([
                    Toggle::make('registration_enabled')
                        ->label(__('event-registrations::messages.config.registration_enabled'))
                        ->default(false)
                        ->live()
                        ->columnSpanFull(),
                ]),

            Fieldset::make(__('event-registrations::messages.config.capacity'))
                ->schema([
                    TextInput::make('max_participants')
                        ->label(__('event-registrations::messages.config.max_participants'))
                        ->helperText(__('event-registrations::messages.config.max_participants_help'))
                        ->numeric()
                        ->minValue(1)
                        ->nullable(),
                    Toggle::make('waiting_list_enabled')
                        ->label(__('event-registrations::messages.config.waiting_list_enabled'))
                        ->default(true),
                    TextInput::make('max_waiting_list')
                        ->label(__('event-registrations::messages.config.max_waiting_list'))
                        ->helperText(__('event-registrations::messages.config.max_waiting_list_help'))
                        ->numeric()
                        ->minValue(0)
                        ->nullable(),
                ])
                ->columns(3)
                ->visible(fn (Get $get): bool => (bool) $get('registration_enabled')),

            Fieldset::make(__('event-registrations::messages.config.dates'))
                ->schema([
                    DateTimePicker::make('registration_opens_at')
                        ->label(__('event-registrations::messages.config.registration_opens_at'))
                        ->native(false)
                        ->displayFormat('d/m/Y H:i')
                        ->nullable(),
                    DateTimePicker::make('registration_closes_at')
                        ->label(__('event-registrations::messages.config.registration_closes_at'))
                        ->native(false)
                        ->displayFormat('d/m/Y H:i')
                        ->after('registration_opens_at')
                        ->nullable(),
                    DateTimePicker::make('cancellation_deadline')
                        ->label(__('event-registrations::messages.config.cancellation_deadline'))
                        ->native(false)
                        ->displayFormat('d/m/Y H:i')
                        ->nullable(),
                ])
                ->columns(3)
                ->visible(fn (Get $get): bool => (bool) $get('registration_enabled')),

            Fieldset::make(__('event-registrations::messages.config.options'))
                ->schema([
                    Toggle::make('requires_confirmation')
                        ->label(__('event-registrations::messages.config.requires_confirmation'))
                        ->helperText(__('event-registrations::messages.config.requires_confirmation_help')),
                    Toggle::make('members_only')
                        ->label(__('event-registrations::messages.config.members_only'))
                        ->helperText(__('event-registrations::messages.config.members_only_help')),
                ])
                ->columns(2)
                ->visible(fn (Get $get): bool => (bool) $get('registration_enabled')),

            Fieldset::make(__('event-registrations::messages.config.notifications'))
                ->schema([
                    TextInput::make('notification_email')
                        ->label(__('event-registrations::messages.config.notification_email'))
                        ->helperText(__('event-registrations::messages.config.notification_email_help'))
                        ->email()
                        ->nullable(),
                ])
                ->visible(fn (Get $get): bool => (bool) $get('registration_enabled')),
        ];
    }

    public function save(): void
    {
        $formData = $this->configForm->getState();
        $eventId = $this->getOwnerRecord()->getKey();

        $service = app(EventRegistrationServiceInterface::class);
        $service->updateConfig(UpdateRegistrationConfigDTO::fromArray([
            'event_id' => $eventId,
            ...$formData,
        ]));

        Notification::make()
            ->title(__('event-registrations::messages.config.saved'))
            ->success()
            ->send();
    }
}
