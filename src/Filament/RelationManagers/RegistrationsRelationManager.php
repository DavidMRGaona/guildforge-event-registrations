<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Filament\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteBulkAction;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Modules\EventRegistrations\Application\Services\EventRegistrationServiceInterface;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationConfigModel;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationModel;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class RegistrationsRelationManager extends RelationManager
{
    /**
     * Key of the tab on the event edit page, used in its `?relation=` query string.
     */
    public const RELATION_KEY = 'registrations';

    protected static string $relationship = 'registrations';

    protected static ?string $recordTitleAttribute = 'id';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('event-registrations::messages.navigation');
    }

    /**
     * Only show this tab if registrations are enabled OR there are existing registrations.
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        if (! $ownerRecord instanceof EventModel) {
            return false;
        }

        // Check if there are any registrations (registrations() is added at runtime, invisible to static analysis)
        $hasRegistrations = EventRegistrationModel::where('event_id', $ownerRecord->id)->exists();
        if ($hasRegistrations) {
            return true;
        }

        // Check if registrations are enabled in the config
        $config = EventRegistrationConfigModel::where('event_id', $ownerRecord->id)->first();

        return $config !== null && $config->registration_enabled === true;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('state')
                    ->label(__('event-registrations::messages.fields.state'))
                    ->options(RegistrationState::options())
                    ->required(),
                Textarea::make('admin_notes')
                    ->label(__('event-registrations::messages.fields.admin_notes'))
                    ->rows(3),
            ]);
    }

    /**
     * Get stats description for the table header.
     */
    private function getStatsDescription(): string
    {
        /** @var EventModel $event */
        $event = $this->getOwnerRecord();

        $counts = EventRegistrationModel::where('event_id', $event->id)
            ->selectRaw('state, COUNT(*) as total')
            ->groupBy('state')
            ->pluck('total', 'state')
            ->toArray();

        $states = [
            RegistrationState::Confirmed->value => 'stats.confirmed',
            RegistrationState::WaitingList->value => 'stats.waiting_list',
            RegistrationState::Pending->value => 'stats.pending',
            RegistrationState::Cancelled->value => 'states.cancelled',
            RegistrationState::Rejected->value => 'states.rejected',
        ];

        $parts = [];

        foreach ($states as $state => $translationKey) {
            $count = $counts[$state] ?? 0;
            if ($count > 0) {
                $parts[] = $count.' '.__('event-registrations::messages.'.$translationKey);
            }
        }

        return implode(' · ', $parts);
    }

    /**
     * Download the event's registrations as a CSV file (UTF-8 with BOM, so spreadsheets read accents).
     */
    private function exportCsv(): StreamedResponse
    {
        /** @var EventModel $event */
        $event = $this->getOwnerRecord();

        $registrations = EventRegistrationModel::query()
            ->with('user')
            ->where('event_id', $event->id)
            ->orderBy('created_at')
            ->get();

        return response()->streamDownload(function () use ($registrations): void {
            $output = fopen('php://output', 'wb');

            if ($output === false) {
                return;
            }

            fwrite($output, "\u{FEFF}");
            fputcsv($output, [
                __('event-registrations::messages.fields.user'),
                __('event-registrations::messages.fields.email'),
                __('event-registrations::messages.fields.state'),
                __('event-registrations::messages.fields.position'),
                __('event-registrations::messages.fields.confirmed_at'),
                __('event-registrations::messages.fields.created_at'),
            ], escape: '');

            foreach ($registrations as $registration) {
                fputcsv($output, [
                    $registration->user->name,
                    $registration->user->email,
                    $registration->state->label(),
                    $registration->position,
                    $registration->confirmed_at?->format('d/m/Y H:i'),
                    $registration->created_at?->format('d/m/Y H:i'),
                ], escape: '');
            }

            fclose($output);
        }, "inscripciones-{$event->slug}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->description(fn (): string => $this->getStatsDescription())
            ->columns([
                TextColumn::make('user.name')
                    ->label(__('event-registrations::messages.fields.user'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.email')
                    ->label(__('event-registrations::messages.fields.email'))
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('state')
                    ->label(__('event-registrations::messages.fields.state'))
                    ->badge()
                    ->color(fn (RegistrationState $state): string => $state->color())
                    ->formatStateUsing(fn (RegistrationState $state): string => $state->label())
                    ->sortable(),
                TextColumn::make('position')
                    ->label(__('event-registrations::messages.fields.position'))
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('confirmed_at')
                    ->label(__('event-registrations::messages.fields.confirmed_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label(__('event-registrations::messages.fields.created_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('state')
                    ->label(__('event-registrations::messages.fields.state'))
                    ->options(RegistrationState::options()),
            ])
            ->headerActions([
                Action::make('export')
                    ->label(__('event-registrations::messages.actions.export'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn (): bool => auth()->user()?->can('export', EventRegistrationModel::class) ?? false)
                    ->action(fn (): StreamedResponse => $this->exportCsv()),
            ])
            ->recordActions([
                Action::make('confirm')
                    ->label(__('event-registrations::messages.actions.confirm'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (EventRegistrationModel $record): bool => $record->state !== RegistrationState::Confirmed && ! $record->state->isFinal())
                    ->requiresConfirmation()
                    ->modalHeading(__('event-registrations::messages.actions.confirm'))
                    ->modalDescription(__('event-registrations::messages.modal.confirm_description'))
                    ->modalSubmitActionLabel(__('event-registrations::messages.actions.confirm'))
                    ->action(function (EventRegistrationModel $record): void {
                        $service = resolve(EventRegistrationServiceInterface::class);
                        $service->confirm((string) $record->id);

                        Notification::make()
                            ->title(__('event-registrations::messages.notifications.confirmed'))
                            ->success()
                            ->send();
                    }),
                Action::make('reject')
                    ->label(__('event-registrations::messages.actions.reject'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (EventRegistrationModel $record): bool => ! $record->state->isFinal())
                    ->requiresConfirmation()
                    ->modalHeading(__('event-registrations::messages.actions.reject'))
                    ->modalDescription(__('event-registrations::messages.modal.reject_description'))
                    ->modalSubmitActionLabel(__('event-registrations::messages.actions.reject'))
                    ->action(function (EventRegistrationModel $record): void {
                        $service = resolve(EventRegistrationServiceInterface::class);
                        $service->reject((string) $record->id);

                        Notification::make()
                            ->title(__('event-registrations::messages.notifications.rejected'))
                            ->success()
                            ->send();
                    }),
                Action::make('move_to_waiting')
                    ->label(__('event-registrations::messages.actions.move_to_waiting'))
                    ->icon('heroicon-o-clock')
                    ->color('warning')
                    ->visible(fn (EventRegistrationModel $record): bool => $record->state === RegistrationState::Confirmed)
                    ->requiresConfirmation()
                    ->modalHeading(__('event-registrations::messages.actions.move_to_waiting'))
                    ->modalDescription(__('event-registrations::messages.modal.move_to_waiting_description'))
                    ->modalSubmitActionLabel(__('event-registrations::messages.actions.move_to_waiting'))
                    ->action(function (EventRegistrationModel $record): void {
                        $service = resolve(EventRegistrationServiceInterface::class);
                        $service->moveToWaitingList((string) $record->id);

                        Notification::make()
                            ->title(__('event-registrations::messages.notifications.moved_to_waiting'))
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('confirm_selected')
                        ->label(__('event-registrations::messages.actions.confirm_selected'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->modalHeading(__('event-registrations::messages.actions.confirm_selected'))
                        ->modalDescription(__('event-registrations::messages.bulk.confirm_description'))
                        ->modalSubmitActionLabel(__('event-registrations::messages.actions.confirm'))
                        ->action(function (Collection $records, RelationManager $livewire): void {
                            $service = resolve(EventRegistrationServiceInterface::class);
                            $count = 0;

                            foreach ($records as $record) {
                                /** @var EventRegistrationModel $registration */
                                $registration = $record;

                                if ($registration->state === RegistrationState::Confirmed) {
                                    continue;
                                }

                                $service->confirm((string) $registration->id);
                                $count++;
                            }

                            if ($count === 0) {
                                Notification::make()
                                    ->title(__('event-registrations::messages.notifications.none_to_confirm'))
                                    ->warning()
                                    ->send();

                                return;
                            }

                            Notification::make()
                                ->title(__('event-registrations::messages.notifications.bulk_confirmed'))
                                ->body($count.' '.__('event-registrations::messages.navigation'))
                                ->success()
                                ->send();

                            // Force Livewire component refresh to avoid stale state
                            $livewire->dispatch('$refresh');
                        }),
                    BulkAction::make('reject_selected')
                        ->label(__('event-registrations::messages.actions.reject_selected'))
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->modalHeading(__('event-registrations::messages.actions.reject_selected'))
                        ->modalDescription(__('event-registrations::messages.bulk.reject_description'))
                        ->modalSubmitActionLabel(__('event-registrations::messages.actions.reject'))
                        ->action(function (Collection $records, RelationManager $livewire): void {
                            $service = resolve(EventRegistrationServiceInterface::class);
                            $count = 0;

                            foreach ($records as $record) {
                                /** @var EventRegistrationModel $registration */
                                $registration = $record;

                                if ($registration->state === RegistrationState::Rejected) {
                                    continue;
                                }

                                $service->reject((string) $registration->id);
                                $count++;
                            }

                            if ($count === 0) {
                                Notification::make()
                                    ->title(__('event-registrations::messages.notifications.none_to_reject'))
                                    ->warning()
                                    ->send();

                                return;
                            }

                            Notification::make()
                                ->title(__('event-registrations::messages.notifications.rejected'))
                                ->body($count.' '.__('event-registrations::messages.navigation'))
                                ->success()
                                ->send();

                            // Force Livewire component refresh to avoid stale state
                            $livewire->dispatch('$refresh');
                        }),
                    DeleteBulkAction::make()
                        ->label(__('common.delete')),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
