<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Filament\Concerns;

use App\Application\Services\TimezoneServiceInterface;
use Carbon\CarbonInterface;
use Filament\Forms\Components\DateTimePicker;

/**
 * The start of the event closes registration and cancellation whatever the configured dates
 * say, so the panel refuses a close date or a cancellation deadline later than it.
 */
trait CapsDatesAtEventStart
{
    private function capAtEventStart(DateTimePicker $picker, ?CarbonInterface $eventStartsAt): DateTimePicker
    {
        if (! $eventStartsAt instanceof CarbonInterface) {
            return $picker;
        }

        $timezone = app(TimezoneServiceInterface::class);

        // The picker's state is the wall time the admin sees, so the cap is the start in that zone
        return $picker
            ->beforeOrEqual($timezone->toDisplayWallTime($eventStartsAt, 'Y-m-d H:i:s'))
            ->validationMessages([
                'before_or_equal' => __('event-registrations::messages.config.not_after_event_start', [
                    'date' => $timezone->format($eventStartsAt),
                ]),
            ]);
    }
}
