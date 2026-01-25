<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Application\DTOs;

use Modules\EventRegistrations\Domain\Entities\EventRegistration;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;

final readonly class EventRegistrationResponseDTO
{
    /**
     * @param  array<string, mixed>  $formData
     */
    public function __construct(
        public string $id,
        public string $eventId,
        public string $userId,
        public RegistrationState $state,
        public ?int $position,
        public array $formData,
        public ?string $notes,
        public ?string $adminNotes,
        public ?string $confirmedAt,
        public ?string $cancelledAt,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {}

    public static function fromEntity(EventRegistration $registration): self
    {
        return new self(
            id: $registration->id()->value,
            eventId: $registration->eventId(),
            userId: $registration->userId(),
            state: $registration->state(),
            position: $registration->position(),
            formData: $registration->formData(),
            notes: $registration->notes(),
            adminNotes: $registration->adminNotes(),
            confirmedAt: $registration->confirmedAt()?->format('c'),
            cancelledAt: $registration->cancelledAt()?->format('c'),
            createdAt: $registration->createdAt()?->format('c'),
            updatedAt: $registration->updatedAt()?->format('c'),
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
            'state' => $this->state->value,
            'state_label' => $this->state->label(),
            'state_color' => $this->state->color(),
            'position' => $this->position,
            'form_data' => $this->formData,
            'notes' => $this->notes,
            'admin_notes' => $this->adminNotes,
            'confirmed_at' => $this->confirmedAt,
            'cancelled_at' => $this->cancelledAt,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
