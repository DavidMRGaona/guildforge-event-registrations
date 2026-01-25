<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Domain\Events;

use DateTimeImmutable;

final readonly class RegistrationRejected
{
    public function __construct(
        public string $registrationId,
        public string $eventId,
        public string $userId,
        public DateTimeImmutable $occurredAt,
    ) {}

    public static function create(
        string $registrationId,
        string $eventId,
        string $userId,
    ): self {
        return new self(
            registrationId: $registrationId,
            eventId: $eventId,
            userId: $userId,
            occurredAt: new DateTimeImmutable,
        );
    }
}
