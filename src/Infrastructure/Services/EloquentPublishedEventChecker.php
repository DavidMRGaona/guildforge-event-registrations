<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Modules\EventRegistrations\Application\Services\PublishedEventCheckerInterface;

final readonly class EloquentPublishedEventChecker implements PublishedEventCheckerInterface
{
    public function isPublished(string $eventId): bool
    {
        return EventModel::query()
            ->whereKey($eventId)
            ->where('is_published', true)
            ->exists();
    }
}
