<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Domain\Events;

use DateTimeImmutable;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;

final readonly class UserRegisteredToEvent
{
    public function __construct(
        public string $registrationId,
        public string $eventId,
        public string $userId,
        public RegistrationState $state,
        public ?int $position,
        public DateTimeImmutable $occurredAt,
    ) {}

    public static function create(
        string $registrationId,
        string $eventId,
        string $userId,
        RegistrationState $state,
        ?int $position = null,
    ): self {
        return new self(
            registrationId: $registrationId,
            eventId: $eventId,
            userId: $userId,
            state: $state,
            position: $position,
            occurredAt: new DateTimeImmutable,
        );
    }
}
