<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Application\DTOs;

final readonly class RegisterToEventDTO
{
    /**
     * @param  array<string, mixed>  $formData
     */
    public function __construct(
        public string $eventId,
        public string $userId,
        public array $formData = [],
        public ?string $notes = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            eventId: $data['event_id'],
            userId: $data['user_id'],
            formData: $data['form_data'] ?? [],
            notes: $data['notes'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'user_id' => $this->userId,
            'form_data' => $this->formData,
            'notes' => $this->notes,
        ];
    }
}
