<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Modules\EventRegistrations\Application\DTOs\UpdateRegistrationConfigDTO;
use Modules\EventRegistrations\Application\Services\EventRegistrationServiceInterface;
use Modules\EventRegistrations\Domain\Repositories\EventRegistrationConfigRepositoryInterface;

/**
 * @property Form $form
 */
final class EventRegistrationConfigPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string $view = 'event-registrations::filament.pages.event-registration-config';

    protected static bool $shouldRegisterNavigation = false;

    public ?string $eventId = null;

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(string $eventId): void
    {
        $this->eventId = $eventId;

        $configRepository = app(EventRegistrationConfigRepositoryInterface::class);
        $config = $configRepository->findByEventOrDefault($eventId);

        $this->form->fill([
            'registration_enabled' => $config->isRegistrationEnabled(),
            'max_participants' => $config->maxParticipants(),
            'waiting_list_enabled' => $config->isWaitingListEnabled(),
            'max_waiting_list' => $config->maxWaitingList(),
            'registration_opens_at' => $config->registrationOpensAt()?->format('Y-m-d H:i:s'),
            'registration_closes_at' => $config->registrationClosesAt()?->format('Y-m-d H:i:s'),
            'cancellation_deadline' => $config->cancellationDeadline()?->format('Y-m-d H:i:s'),
            'requires_confirmation' => $config->requiresConfirmation(),
            'requires_payment' => $config->requiresPayment(),
            'members_only' => $config->isMembersOnly(),
            'confirmation_message' => $config->confirmationMessage(),
        ]);
    }

    public function getTitle(): string|Htmlable
    {
        return __('event-registrations::messages.config.title');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('event-registrations::messages.config.general'))
                    ->schema([
                        Toggle::make('registration_enabled')
                            ->label(__('event-registrations::messages.config.registration_enabled'))
                            ->default(false),
                        Fieldset::make(__('event-registrations::messages.config.capacity'))
                            ->schema([
                                TextInput::make('max_participants')
                                    ->label(__('event-registrations::messages.config.max_participants'))
                                    ->numeric()
                                    ->minValue(1)
                                    ->nullable()
                                    ->helperText(__('event-registrations::messages.config.max_participants_help')),
                                Toggle::make('waiting_list_enabled')
                                    ->label(__('event-registrations::messages.config.waiting_list_enabled'))
                                    ->default(true),
                                TextInput::make('max_waiting_list')
                                    ->label(__('event-registrations::messages.config.max_waiting_list'))
                                    ->numeric()
                                    ->minValue(1)
                                    ->nullable()
                                    ->helperText(__('event-registrations::messages.config.max_waiting_list_help')),
                            ])->columns(2),
                    ]),

                Section::make(__('event-registrations::messages.config.dates'))
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
                    ])->columns(3),

                Section::make(__('event-registrations::messages.config.options'))
                    ->schema([
                        Toggle::make('requires_confirmation')
                            ->label(__('event-registrations::messages.config.requires_confirmation'))
                            ->helperText(__('event-registrations::messages.config.requires_confirmation_help')),
                        Toggle::make('requires_payment')
                            ->label(__('event-registrations::messages.config.requires_payment'))
                            ->helperText(__('event-registrations::messages.config.requires_payment_help')),
                        Toggle::make('members_only')
                            ->label(__('event-registrations::messages.config.members_only'))
                            ->helperText(__('event-registrations::messages.config.members_only_help')),
                    ])->columns(3),

                Section::make(__('event-registrations::messages.config.messages'))
                    ->schema([
                        Textarea::make('confirmation_message')
                            ->label(__('event-registrations::messages.config.confirmation_message'))
                            ->helperText(__('event-registrations::messages.config.confirmation_message_help'))
                            ->rows(3),
                    ])->collapsed(),
            ])
            ->statePath('data');
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('event-registrations::messages.config.save'))
                ->submit('save'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $service = app(EventRegistrationServiceInterface::class);
        $service->updateConfig(UpdateRegistrationConfigDTO::fromArray([
            'event_id' => $this->eventId,
            ...$data,
        ]));

        Notification::make()
            ->title(__('event-registrations::messages.config.saved'))
            ->success()
            ->send();
    }
}
