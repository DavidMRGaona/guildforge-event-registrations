<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Domain\Exceptions;

use DomainException;

final class MembersOnlyException extends DomainException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function membersOnly(string $eventId): self
    {
        return new self("Registration for event {$eventId} is restricted to members.");
    }
}
