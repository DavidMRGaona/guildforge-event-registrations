<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Domain\Exceptions;

use DomainException;

final class EventFullException extends DomainException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function noSpotsAvailable(string $eventId): self
    {
        return new self("Event {$eventId} has no spots available.");
    }

    public static function noSpotsToConfirm(string $eventId): self
    {
        return new self("Event {$eventId} has no free spot to confirm one more registration.");
    }

    public static function waitingListFull(string $eventId): self
    {
        return new self("Event {$eventId} waiting list is full.");
    }
}
