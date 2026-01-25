<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Domain\Exceptions;

use DomainException;

final class AlreadyRegisteredException extends DomainException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function userAlreadyRegistered(string $eventId, string $userId): self
    {
        return new self("User {$userId} is already registered for event {$eventId}.");
    }
}
