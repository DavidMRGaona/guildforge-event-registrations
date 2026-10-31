<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Infrastructure\Surveys;

use App\Application\Services\TimezoneServiceInterface;
use App\Domain\Surveys\Events\SurveySubjectEnded;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Domain\Services\Clock;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationModel;

/**
 * Events have no "ended" moment of their own, so this tells the surveys module (through the
 * core's SurveySubjectEnded) about each survey subject whose end has passed and that had
 * someone to ask. Each event is claimed in a table before it is announced, so it is announced
 * once even when two runs overlap. Instants are compared, never wall-clock times, so the hour
 * repeated in October does not end an event early.
 */
final readonly class EndedEventAnnouncer
{
    public const string TABLE = 'event_registrations_survey_ends';

    public function __construct(
        private SurveyableEvents $events,
        private Clock $clock,
        private TimezoneServiceInterface $timezone,
    ) {}

    /**
     * @return int How many events were announced
     */
    public function announce(): int
    {
        $now = $this->timezone->toStorage($this->clock->now());

        $ended = $this->events->query()
            ->where('end_date', '<=', $now)
            ->whereNotIn('id', DB::table(self::TABLE)->select('event_id'))
            ->whereIn('id', EventRegistrationModel::query()->where('state', RegistrationState::Confirmed->value)->select('event_id'))
            ->orderBy('end_date')
            ->get();

        $announced = 0;

        foreach ($ended as $event) {
            if (! $this->claim($event, $now)) {
                continue;
            }

            $endedAt = DateTimeImmutable::createFromInterface($event->end_date);
            DB::afterCommit(static fn () => event(new SurveySubjectEnded(EventSurveySubjectSource::TYPE, $event->id, $endedAt)));
            $announced++;
        }

        return $announced;
    }

    private function claim(EventModel $event, DateTimeImmutable $now): bool
    {
        return DB::table(self::TABLE)->insertOrIgnore([
            'event_id' => $event->id,
            'announced_at' => $now->format('Y-m-d H:i:s'),
        ]) === 1;
    }
}
