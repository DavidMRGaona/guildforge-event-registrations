<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Filament\Pages;

use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Filament\Clusters\SettingsCluster;
use App\Filament\Concerns\ChecksPermissions;
use App\Filament\Concerns\HasKnowledgeGuideAction;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Defaults of the registrations and the emails the module sends, in the «Configuración» cluster.
 * Stored in the same `module_settings:event-registrations` row the generic modules page used.
 *
 * @property Schema $form
 */
final class RegistrationsSettings extends Page implements HasForms
{
    use ChecksPermissions;
    use HasKnowledgeGuideAction;
    use InteractsWithForms;

    public const string PERMISSION = 'registrations.manage_settings';

    private const string MODULE = 'event-registrations';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $cluster = SettingsCluster::class;

    protected static ?int $navigationSort = 127;

    protected static ?string $slug = 'inscripciones';

    protected string $view = 'filament.pages.simple-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('event-registrations::messages.settings.navigation');
    }

    public function getTitle(): string
    {
        return __('event-registrations::messages.settings.title');
    }

    public static function canAccess(): bool
    {
        return self::userCan(self::MODULE.':'.self::PERMISSION);
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);

        $this->form->fill(app(ModuleManagerServiceInterface::class)->getSettings(new ModuleName(self::MODULE)));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                        Toggle::make('send_promotion_email')
                            ->label(__('event-registrations::messages.settings.send_promotion_email'))
                            ->default(true),
                        Toggle::make('send_cancellation_email')
                            ->label(__('event-registrations::messages.settings.send_cancellation_email'))
                            ->default(true),
                        Toggle::make('send_reminder_email')
                            ->label(__('event-registrations::messages.settings.send_reminder_email'))
                            ->helperText(__('event-registrations::messages.settings.send_reminder_email_help'))
                            ->default(false),
                        TextInput::make('reminder_hours_before')
                            ->label(__('event-registrations::messages.settings.reminder_hours_before'))
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(168)
                            ->default(24),
                    ]),
            ])
            ->statePath('data');
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return array_filter([$this->knowledgeGuideAction('event-registrations.panel.settings')]);
    }

    /**
     * @return array<Action>
     */
    public function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('event-registrations::messages.settings.save'))
                ->submit('save'),
        ];
    }

    public function save(): void
    {
        // Livewire methods can be called without the page's own checks
        abort_unless(self::canAccess(), 403);

        app(ModuleManagerServiceInterface::class)->updateSettings(new ModuleName(self::MODULE), $this->form->getState());

        Notification::make()
            ->title(__('event-registrations::messages.settings.saved'))
            ->success()
            ->send();
    }
}
