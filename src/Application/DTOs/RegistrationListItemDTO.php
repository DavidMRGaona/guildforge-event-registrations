<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Application\DTOs;

use Modules\EventRegistrations\Domain\Entities\EventRegistration;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;

final readonly class RegistrationListItemDTO
{
    public function __construct(
        public string $id,
        public string $eventId,
        public string $userId,
        public ?string $userName,
        public ?string $userEmail,
        public RegistrationState $state,
        public ?int $position,
        public ?string $confirmedAt,
        public ?string $createdAt,
    ) {}

    public static function fromEntity(
        EventRegistration $registration,
        ?string $userName = null,
        ?string $userEmail = null,
    ): self {
        return new self(
            id: $registration->id()->value,
            eventId: $registration->eventId(),
            userId: $registration->userId(),
            userName: $userName,
            userEmail: $userEmail,
            state: $registration->state(),
            position: $registration->position(),
            confirmedAt: $registration->confirmedAt()?->format('c'),
            createdAt: $registration->createdAt()?->format('c'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->eventId,
            'user_id' => $this->userId,
            'user_name' => $this->userName,
            'user_email' => $this->userEmail,
            'state' => $this->state->value,
            'state_label' => $this->state->label(),
            'state_color' => $this->state->color(),
            'position' => $this->position,
            'confirmed_at' => $this->confirmedAt,
            'created_at' => $this->createdAt,
        ];
    }
}
