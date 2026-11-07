<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Domain\Exceptions;

use DomainException;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;

/**
 * A registration was asked to change to a state it cannot reach from the one it is in.
 */
final class InvalidRegistrationTransitionException extends DomainException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function cannotConfirm(string $registrationId, RegistrationState $state): self
    {
        return new self("Registration {$registrationId} cannot be confirmed from state {$state->value}.");
    }

    public static function cannotReject(string $registrationId, RegistrationState $state): self
    {
        return new self("Registration {$registrationId} cannot be rejected from state {$state->value}.");
    }

    public static function cannotMoveToWaitingList(string $registrationId, RegistrationState $state): self
    {
        return new self("Registration {$registrationId} cannot move to the waiting list from state {$state->value}.");
    }
}
