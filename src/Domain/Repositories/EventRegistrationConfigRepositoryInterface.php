<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Domain\Repositories;

use Modules\EventRegistrations\Domain\Entities\EventRegistrationConfig;

interface EventRegistrationConfigRepositoryInterface
{
    /**
     * Save a registration config (create or update).
     */
    public function save(EventRegistrationConfig $config): void;

    /**
     * Find a config by event ID.
     */
    public function findByEvent(string $eventId): ?EventRegistrationConfig;

    /**
     * Find a config by event ID or return a default config.
     */
    public function findByEventOrDefault(string $eventId): EventRegistrationConfig;

    /**
     * Delete a config.
     */
    public function delete(string $eventId): void;

    /**
     * Check if a config exists for an event.
     */
    public function exists(string $eventId): bool;
}
