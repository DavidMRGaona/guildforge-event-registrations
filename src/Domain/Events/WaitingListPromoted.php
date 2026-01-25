<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Domain\Events;

use DateTimeImmutable;

final readonly class WaitingListPromoted
{
    public function __construct(
        public string $registrationId,
        public string $eventId,
        public string $userId,
        public int $previousPosition,
        public DateTimeImmutable $occurredAt,
    ) {}

    public static function create(
        string $registrationId,
        string $eventId,
        string $userId,
        int $previousPosition,
    ): self {
        return new self(
            registrationId: $registrationId,
            eventId: $eventId,
            userId: $userId,
            previousPosition: $previousPosition,
            occurredAt: new DateTimeImmutable,
        );
    }
}
