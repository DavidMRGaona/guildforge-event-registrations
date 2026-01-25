<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Filament\RelationManagers;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Domain\Events\RegistrationConfirmed;
use Modules\EventRegistrations\Domain\Events\RegistrationRejected;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationConfigModel;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationModel;

final class RegistrationsRelationManager extends RelationManager
{
    protected static string $relationship = 'registrations';

    protected static ?string $recordTitleAttribute = 'id';

    public static function getTitle($ownerRecord, string $pageClass): string
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

        // Check if there are any registrations
        $hasRegistrations = $ownerRecord->registrations()->exists();
        if ($hasRegistrations) {
            return true;
        }

        // Check if registrations are enabled in the config
        $config = EventRegistrationConfigModel::where('event_id', $ownerRecord->id)->first();

        return $config !== null && $config->registration_enabled === true;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
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
                // Export action placeholder
                Action::make('export')
                    ->label(__('event-registrations::messages.actions.export'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(function (): void {
                        // TODO: Implement export
                        Notification::make()
                            ->title('Export coming soon')
                            ->info()
                            ->send();
                    }),
            ])
            ->actions([
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
                        $record->state = RegistrationState::Confirmed;
                        $record->confirmed_at = now();
                        $record->position = null;
                        $record->save();

                        Event::dispatch(RegistrationConfirmed::create(
                            registrationId: (string) $record->id,
                            eventId: (string) $record->event_id,
                            userId: (string) $record->user_id,
                        ));

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
                        $record->state = RegistrationState::Rejected;
                        $record->save();

                        Event::dispatch(RegistrationRejected::create(
                            registrationId: (string) $record->id,
                            eventId: (string) $record->event_id,
                            userId: (string) $record->user_id,
                        ));

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
                        $maxPosition = EventRegistrationModel::where('event_id', $record->event_id)
                            ->where('state', RegistrationState::WaitingList->value)
                            ->max('position');

                        $record->state = RegistrationState::WaitingList;
                        $record->position = ($maxPosition ?? 0) + 1;
                        $record->confirmed_at = null;
                        $record->save();

                        Notification::make()
                            ->title(__('event-registrations::messages.notifications.moved_to_waiting'))
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
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
                            $count = 0;

                            foreach ($records as $record) {
                                /** @var EventRegistrationModel $registration */
                                $registration = EventRegistrationModel::find($record->getKey());

                                if ($registration === null || $registration->state === RegistrationState::Confirmed) {
                                    continue;
                                }

                                $registration->state = RegistrationState::Confirmed;
                                $registration->confirmed_at = now();
                                $registration->position = null;
                                $registration->save();

                                Event::dispatch(RegistrationConfirmed::create(
                                    registrationId: (string) $registration->id,
                                    eventId: (string) $registration->event_id,
                                    userId: (string) $registration->user_id,
                                ));

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
                            $count = 0;

                            foreach ($records as $record) {
                                /** @var EventRegistrationModel $registration */
                                $registration = EventRegistrationModel::find($record->getKey());

                                if ($registration === null || $registration->state === RegistrationState::Rejected) {
                                    continue;
                                }

                                $registration->state = RegistrationState::Rejected;
                                $registration->save();

                                Event::dispatch(RegistrationRejected::create(
                                    registrationId: (string) $registration->id,
                                    eventId: (string) $registration->event_id,
                                    userId: (string) $registration->user_id,
                                ));

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
