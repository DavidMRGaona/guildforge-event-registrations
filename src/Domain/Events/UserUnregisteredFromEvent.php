<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Domain\Events;

use DateTimeImmutable;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;

final readonly class UserUnregisteredFromEvent
{
    public function __construct(
        public string $registrationId,
        public string $eventId,
        public string $userId,
        public RegistrationState $previousState,
        public DateTimeImmutable $occurredAt,
    ) {}

    public static function create(
        string $registrationId,
        string $eventId,
        string $userId,
        RegistrationState $previousState,
    ): self {
        return new self(
            registrationId: $registrationId,
            eventId: $eventId,
            userId: $userId,
            previousState: $previousState,
            occurredAt: new DateTimeImmutable,
        );
    }
}
