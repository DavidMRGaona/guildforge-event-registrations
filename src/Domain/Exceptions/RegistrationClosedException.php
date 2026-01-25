<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Domain\Exceptions;

use DomainException;

final class RegistrationClosedException extends DomainException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function notYetOpen(string $eventId): self
    {
        return new self("Registration for event {$eventId} is not yet open.");
    }

    public static function alreadyClosed(string $eventId): self
    {
        return new self("Registration for event {$eventId} has closed.");
    }

    public static function disabled(string $eventId): self
    {
        return new self("Registration for event {$eventId} is disabled.");
    }
}
