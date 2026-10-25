<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Filament\Concerns;

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

        return $picker
            ->beforeOrEqual($eventStartsAt->format('Y-m-d H:i:s'))
            ->validationMessages([
                'before_or_equal' => __('event-registrations::messages.config.not_after_event_start', [
                    'date' => $eventStartsAt->format('d/m/Y H:i'),
                ]),
            ]);
    }
}
