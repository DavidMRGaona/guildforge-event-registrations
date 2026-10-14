<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Application\DTOs;

/**
 * One of the user's registrations with its event, as GET /mis-inscripciones returns it.
 * The event is null when it is not published, so a draft is never described to the user.
 */
final readonly class UserRegistrationDTO
{
    public function __construct(
        public EventRegistrationResponseDTO $registration,
        public ?RegisteredEventDTO $event,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            ...$this->registration->toArray(),
            'event' => $this->event?->toArray(),
        ];
    }
}
