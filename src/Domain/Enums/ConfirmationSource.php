<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Domain\Enums;

/**
 * What caused a registration to become confirmed.
 */
enum ConfirmationSource: string
{
    /** An administrator confirmed a pending registration. */
    case Manual = 'manual';

    /** The event does not require confirmation, so the registration was confirmed on sign-up. */
    case Registration = 'registration';

    /** A spot opened up and the registration was promoted from the waiting list. */
    case WaitingListPromotion = 'waiting_list_promotion';
}
