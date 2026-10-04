<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Domain\Events;

use DateTimeImmutable;
use Modules\EventRegistrations\Domain\Enums\ConfirmationSource;

final readonly class RegistrationConfirmed
{
    public function __construct(
        public string $registrationId,
        public string $eventId,
        public string $userId,
        public DateTimeImmutable $occurredAt,
        public ConfirmationSource $source = ConfirmationSource::Manual,
    ) {}

    public static function create(
        string $registrationId,
        string $eventId,
        string $userId,
        ConfirmationSource $source = ConfirmationSource::Manual,
    ): self {
        return new self(
            registrationId: $registrationId,
            eventId: $eventId,
            userId: $userId,
            occurredAt: new DateTimeImmutable,
            source: $source,
        );
    }
}
