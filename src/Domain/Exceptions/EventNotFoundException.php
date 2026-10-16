<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Domain\Exceptions;

use DomainException;

final class EventNotFoundException extends DomainException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function notPublished(string $eventId): self
    {
        return new self("Event {$eventId} does not exist or is not published.");
    }
}
