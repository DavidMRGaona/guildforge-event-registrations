<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Infrastructure\Surveys;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationConfigModel;

/**
 * The events offered as survey subjects: published, with registrations on. An event without
 * registrations has nobody to ask.
 */
final readonly class SurveyableEvents
{
    /**
     * @return Builder<EventModel>
     */
    public function query(): Builder
    {
        return EventModel::query()
            ->where('is_published', true)
            ->whereIn('id', EventRegistrationConfigModel::query()->where('registration_enabled', true)->select('event_id'));
    }

    public function find(string $eventId): ?EventModel
    {
        return Str::isUuid($eventId) ? $this->query()->whereKey($eventId)->first() : null;
    }
}
