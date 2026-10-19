<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationModel;
use Modules\EventRegistrations\Notifications\EventReminderNotification;

/**
 * Reminds confirmed registrations of events that start within the configured hours. Each
 * registration is reminded once; nothing runs while the reminder is off.
 */
final class SendEventRemindersCommand extends Command
{
    private const int DEFAULT_HOURS_BEFORE = 24;

    private const int MAX_HOURS_BEFORE = 168;

    protected $signature = 'event-registrations:send-reminders';

    protected $description = 'Remind confirmed registrations of events that start soon';

    public function handle(): int
    {
        if (! (bool) config('modules.settings.event-registrations.send_reminder_email', false)) {
            $this->info('Event reminders are disabled.');

            return self::SUCCESS;
        }

        $now = CarbonImmutable::now();
        $until = $now->addHours(self::hoursBefore());
        $sent = 0;

        $registrations = EventRegistrationModel::query()
            ->where('state', RegistrationState::Confirmed->value)
            ->whereNull('reminder_sent_at')
            // An anonymised account keeps only a placeholder address
            ->whereHas('user', fn ($user) => $user->whereNull('anonymized_at'))
            ->whereHas('event', fn ($event) => $event
                ->where('is_published', true)
                ->where('start_date', '>', $now)
                ->where('start_date', '<=', $until))
            ->with(['event', 'user'])
            ->get();

        foreach ($registrations as $registration) {
            $user = $registration->user;

            if ($user === null || $registration->event === null) {
                continue;
            }

            // Claimed before sending, so a run started by hand next to the scheduled one never doubles it
            $claimed = EventRegistrationModel::query()
                ->whereKey($registration->getKey())
                ->whereNull('reminder_sent_at')
                ->update(['reminder_sent_at' => $now]) === 1;

            if (! $claimed) {
                continue;
            }

            $user->notify(new EventReminderNotification($registration->event));
            $sent++;
        }

        $this->info("Sent {$sent} reminder(s).");

        return self::SUCCESS;
    }

    private static function hoursBefore(): int
    {
        $hours = config('modules.settings.event-registrations.reminder_hours_before');

        return is_numeric($hours) && (int) $hours >= 1 ? min((int) $hours, self::MAX_HOURS_BEFORE) : self::DEFAULT_HOURS_BEFORE;
    }
}
