<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Domain\Exceptions;

use DomainException;

final class RegistrationNotFoundException extends DomainException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function withId(string $id): self
    {
        return new self("Registration with ID {$id} not found.");
    }

    public static function forUserAndEvent(string $userId, string $eventId): self
    {
        return new self("Registration for user {$userId} and event {$eventId} not found.");
    }
}
