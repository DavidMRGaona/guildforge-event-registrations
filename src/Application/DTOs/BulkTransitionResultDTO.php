<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Application\DTOs;

/**
 * What a bulk confirm or reject did: how many registrations changed, how many were skipped
 * because their state cannot change that way, and how many because no spot was left.
 */
final readonly class BulkTransitionResultDTO
{
    public function __construct(
        public int $changed,
        public int $skippedInvalid = 0,
        public int $skippedFull = 0,
    ) {}
}
