<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Filament\RelationManagers;

use App\Application\Services\TimezoneServiceInterface;
use App\Filament\Concerns\HasKnowledgeGuideAction;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Modules\EventRegistrations\Application\DTOs\BulkTransitionResultDTO;
use Modules\EventRegistrations\Application\Services\EventRegistrationServiceInterface;
use Modules\EventRegistrations\Application\Services\WaitingListServiceInterface;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Domain\Exceptions\EventFullException;
use Modules\EventRegistrations\Domain\Repositories\EventRegistrationRepositoryInterface;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationConfigModel;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationModel;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class RegistrationsRelationManager extends RelationManager
{
    use HasKnowledgeGuideAction;

    /**
     * Key of the tab on the event edit page, used in its `?relation=` query string.
     */
    public const string RELATION_KEY = 'registrations';

    protected static string $relationship = 'registrations';

    protected static ?string $recordTitleAttribute = 'id';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('event-registrations::messages.navigation');
    }

    /**
     * Only show this tab to who may see registrations, and only if registrations are enabled OR
     * there are existing registrations.
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        if (! $ownerRecord instanceof EventModel || ! self::userCanViewRegistrations()) {
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

    /**
     * The tab is a Livewire component of its own: refuse it to whoever may not see it, whatever the page did.
     */
    public function mount(): void
    {
        abort_unless(self::userCanViewRegistrations(), 403);

        parent::mount();
    }

    private static function userCanViewRegistrations(): bool
    {
        return auth()->user()?->can('viewAny', EventRegistrationModel::class) ?? false;
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
     * Download the event's registrations as a CSV file: UTF-8 with BOM, so spreadsheets read accents,
     * and `;` as separator, which Excel expects with Spanish regional settings. Deactivated
     * (soft-deleted) users are included.
     */
    private function exportCsv(): StreamedResponse
    {
        /** @var EventModel $event */
        $event = $this->getOwnerRecord();

        $registrations = EventRegistrationModel::query()
            ->with(['user' => fn ($query) => $query->withTrashed()])
            ->where('event_id', $event->id)
            ->orderBy('created_at')
            ->get();
        $timezone = app(TimezoneServiceInterface::class);

        return response()->streamDownload(function () use ($registrations, $timezone): void {
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
            ], separator: ';', escape: '');

            foreach ($registrations as $registration) {
                fputcsv($output, array_map($this->neutraliseCsvFormula(...), [
                    $registration->user->name ?? __('event-registrations::messages.export.deleted_user'),
                    $registration->user->email ?? '',
                    $registration->state->label(),
                    $registration->position,
                    $registration->confirmed_at !== null ? $timezone->format($registration->confirmed_at) : null,
                    $registration->created_at !== null ? $timezone->format($registration->created_at) : null,
                ]), separator: ';', escape: '');
            }

            fclose($output);
        }, "inscripciones-{$event->slug}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Prefix values a spreadsheet would run as a formula (CSV injection) with a single quote.
     */
    private function neutraliseCsvFormula(mixed $value): mixed
    {
        if (is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) === 1) {
            return "'".$value;
        }

        return $value;
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
            ->headerActions(array_filter([
                Action::make('export')
                    ->label(__('event-registrations::messages.actions.export'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn (): bool => auth()->user()?->can('export', EventRegistrationModel::class) ?? false)
                    ->action(fn (): StreamedResponse => $this->exportCsv()),
                $this->knowledgeGuideAction('event-registrations.panel.manage-registrations'),
            ]))
            ->recordActions([
                Action::make('confirm')
                    ->authorize('update')
                    ->label(__('event-registrations::messages.actions.confirm'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (EventRegistrationModel $record): bool => $record->state->canBeConfirmed())
                    ->requiresConfirmation()
                    ->modalHeading(__('event-registrations::messages.actions.confirm'))
                    ->modalDescription(__('event-registrations::messages.modal.confirm_description'))
                    ->modalSubmitActionLabel(__('event-registrations::messages.actions.confirm'))
                    ->action(function (EventRegistrationModel $record): void {
                        $service = resolve(EventRegistrationServiceInterface::class);

                        try {
                            $service->confirm((string) $record->id);
                        } catch (EventFullException) {
                            Notification::make()->title(__('event-registrations::messages.notifications.event_full'))->warning()->send();

                            return;
                        }

                        Notification::make()
                            ->title(__('event-registrations::messages.notifications.confirmed'))
                            ->success()
                            ->send();
                    }),
                Action::make('reject')
                    ->authorize('update')
                    ->label(__('event-registrations::messages.actions.reject'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (EventRegistrationModel $record): bool => $record->state->canBeRejected())
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
                    ->authorize('update')
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
                // Editing the state or deleting a confirmed registration may free a spot
                EditAction::make()
                    ->after(fn (EventRegistrationModel $record) => $this->fillFreeSpotsAfterChange((string) $record->event_id, $record)),
                DeleteAction::make()
                    ->after(fn (EventRegistrationModel $record) => $this->fillFreeSpotsAfterChange((string) $record->event_id, $record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('confirm_selected')
                        ->authorize('updateAny')
                        ->label(__('event-registrations::messages.actions.confirm_selected'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->modalHeading(__('event-registrations::messages.actions.confirm_selected'))
                        ->modalDescription(__('event-registrations::messages.bulk.confirm_description'))
                        ->modalSubmitActionLabel(__('event-registrations::messages.actions.confirm'))
                        ->action(function (Collection $records, RelationManager $livewire): void {
                            $result = resolve(EventRegistrationServiceInterface::class)->confirmMany(
                                $records->map(fn ($record): string => (string) $record->getKey())->values()->all()
                            );

                            $this->notifyBulkResult(
                                $result,
                                successTitle: 'notifications.bulk_confirmed',
                                noneTitle: 'notifications.none_to_confirm',
                                changedLine: 'bulk.confirmed_count',
                                skippedInvalidLine: 'bulk.skipped_cannot_confirm',
                            );

                            // Force Livewire component refresh to avoid stale state
                            $livewire->dispatch('$refresh');
                        }),
                    BulkAction::make('reject_selected')
                        ->authorize('updateAny')
                        ->label(__('event-registrations::messages.actions.reject_selected'))
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->modalHeading(__('event-registrations::messages.actions.reject_selected'))
                        ->modalDescription(__('event-registrations::messages.bulk.reject_description'))
                        ->modalSubmitActionLabel(__('event-registrations::messages.actions.reject'))
                        ->action(function (Collection $records, RelationManager $livewire): void {
                            $result = resolve(EventRegistrationServiceInterface::class)->rejectMany(
                                $records->map(fn ($record): string => (string) $record->getKey())->values()->all()
                            );

                            $this->notifyBulkResult(
                                $result,
                                successTitle: 'notifications.bulk_rejected',
                                noneTitle: 'notifications.none_to_reject',
                                changedLine: 'bulk.rejected_count',
                                skippedInvalidLine: 'bulk.skipped_cannot_reject',
                            );

                            // Force Livewire component refresh to avoid stale state
                            $livewire->dispatch('$refresh');
                        }),
                    DeleteBulkAction::make()
                        ->label(__('common.delete'))
                        ->after(fn (RelationManager $livewire) => $this->fillFreeSpotsAfterChange((string) $livewire->getOwnerRecord()->getKey())),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * Say what a bulk confirm or reject did: how many changed and how many it skipped, and
     * why. A warning when nothing changed.
     */
    private function notifyBulkResult(
        BulkTransitionResultDTO $result,
        string $successTitle,
        string $noneTitle,
        string $changedLine,
        string $skippedInvalidLine,
    ): void {
        $lines = [];

        if ($result->changed > 0) {
            $lines[] = trans_choice("event-registrations::messages.{$changedLine}", $result->changed);
        }

        if ($result->skippedInvalid > 0) {
            $lines[] = trans_choice("event-registrations::messages.{$skippedInvalidLine}", $result->skippedInvalid);
        }

        if ($result->skippedFull > 0) {
            $lines[] = trans_choice('event-registrations::messages.bulk.skipped_full', $result->skippedFull);
        }

        $notification = Notification::make()
            ->title(__('event-registrations::messages.'.($result->changed > 0 ? $successTitle : $noneTitle)))
            ->body($lines === [] ? null : implode(' ', $lines));

        ($result->changed > 0 ? $notification->success() : $notification->warning())->send();
    }

    /**
     * Fill the spots an Eloquent panel action may have freed (edit, delete, bulk delete).
     * A record just edited INTO the waiting list stays on it, at the end of the line,
     * and is never the one promoted.
     */
    private function fillFreeSpotsAfterChange(string $eventId, ?EventRegistrationModel $record = null): void
    {
        $excludeId = null;

        // Only a change INTO the waiting list goes to the end of the line; any other edit of a
        // waiting row (e.g. its notes) leaves it in its place in the queue
        if ($record instanceof EventRegistrationModel && $record->exists && $record->wasChanged('state') && $record->state === RegistrationState::WaitingList) {
            $record->position = resolve(EventRegistrationRepositoryInterface::class)->getNextWaitingListPosition($eventId);
            $record->save();

            $excludeId = (string) $record->id;
        }

        resolve(WaitingListServiceInterface::class)->fillFreeSpots($eventId, $excludeId);
    }
}
