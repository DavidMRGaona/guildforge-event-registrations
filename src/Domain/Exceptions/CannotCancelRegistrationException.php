<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Domain\Exceptions;

use DomainException;

final class CannotCancelRegistrationException extends DomainException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function alreadyCancelled(string $id): self
    {
        return new self("Registration {$id} is already cancelled.");
    }

    public static function deadlinePassed(string $eventId): self
    {
        return new self("Cancellation deadline for event {$eventId} has passed.");
    }

    public static function registrationRejected(string $id): self
    {
        return new self("Registration {$id} has been rejected and cannot be cancelled.");
    }
}
