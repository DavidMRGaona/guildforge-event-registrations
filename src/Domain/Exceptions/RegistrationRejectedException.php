<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Domain\Exceptions;

use DomainException;

/**
 * The organisers rejected the person's registration: only they can change it, from the panel.
 * A cancelled registration, by contrast, may register again.
 */
final class RegistrationRejectedException extends DomainException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function cannotRegisterAgain(string $eventId, string $userId): self
    {
        return new self("The registration of user {$userId} for event {$eventId} was rejected and cannot be requested again.");
    }
}
