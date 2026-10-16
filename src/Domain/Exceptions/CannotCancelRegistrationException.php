<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Domain\Exceptions;

use DomainException;

final class CannotCancelRegistrationException extends DomainException
{
    private function __construct(string $message, private readonly bool $eventStarted = false)
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

    public static function eventStarted(string $eventId): self
    {
        return new self("Registrations for event {$eventId} cannot be cancelled once it has started.", eventStarted: true);
    }

    /**
     * Whether the cancellation was refused because the event has started (or its start is unknown).
     */
    public function isEventStarted(): bool
    {
        return $this->eventStarted;
    }
}
