<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationModel;

final class RegistrationStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $totalConfirmed = EventRegistrationModel::query()
            ->where('state', RegistrationState::Confirmed->value)
            ->count();

        $totalWaiting = EventRegistrationModel::query()
            ->where('state', RegistrationState::WaitingList->value)
            ->count();

        $totalPending = EventRegistrationModel::query()
            ->where('state', RegistrationState::Pending->value)
            ->count();

        return [
            Stat::make(
                __('event-registrations::messages.stats.confirmed'),
                $totalConfirmed
            )
                ->description(__('event-registrations::messages.stats.confirmed_description'))
                ->color('success')
                ->icon('heroicon-o-check-circle'),

            Stat::make(
                __('event-registrations::messages.stats.waiting_list'),
                $totalWaiting
            )
                ->description(__('event-registrations::messages.stats.waiting_list_description'))
                ->color('warning')
                ->icon('heroicon-o-clock'),

            Stat::make(
                __('event-registrations::messages.stats.pending'),
                $totalPending
            )
                ->description(__('event-registrations::messages.stats.pending_description'))
                ->color('info')
                ->icon('heroicon-o-question-mark-circle'),
        ];
    }
}
