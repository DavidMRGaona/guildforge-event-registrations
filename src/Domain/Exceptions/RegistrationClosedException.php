<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Domain\Exceptions;

use DomainException;

final class RegistrationClosedException extends DomainException
{
    private function __construct(string $message, private readonly bool $eventStarted = false)
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

    public static function eventStarted(string $eventId): self
    {
        return new self("Registration for event {$eventId} closed when the event started.", eventStarted: true);
    }

    /**
     * Whether registration closed because the event has started (or its start is unknown).
     */
    public function isEventStarted(): bool
    {
        return $this->eventStarted;
    }
}
