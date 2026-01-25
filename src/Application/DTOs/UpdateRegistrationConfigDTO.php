<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Application\DTOs;

use DateTimeImmutable;

final readonly class UpdateRegistrationConfigDTO
{
    /**
     * @param  array<array{name: string, label: string, type: string, required: bool, options?: array<string>}>  $customFields
     */
    public function __construct(
        public string $eventId,
        public bool $registrationEnabled = true,
        public ?int $maxParticipants = null,
        public bool $waitingListEnabled = true,
        public ?int $maxWaitingList = null,
        public ?DateTimeImmutable $registrationOpensAt = null,
        public ?DateTimeImmutable $registrationClosesAt = null,
        public ?DateTimeImmutable $cancellationDeadline = null,
        public bool $requiresConfirmation = false,
        public bool $requiresPayment = false,
        public bool $membersOnly = false,
        public array $customFields = [],
        public ?string $confirmationMessage = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $maxParticipants = $data['max_participants'] ?? null;
        $maxWaitingList = $data['max_waiting_list'] ?? null;

        return new self(
            eventId: $data['event_id'],
            registrationEnabled: (bool) ($data['registration_enabled'] ?? true),
            maxParticipants: $maxParticipants !== null && $maxParticipants !== '' ? (int) $maxParticipants : null,
            waitingListEnabled: (bool) ($data['waiting_list_enabled'] ?? true),
            maxWaitingList: $maxWaitingList !== null && $maxWaitingList !== '' ? (int) $maxWaitingList : null,
            registrationOpensAt: isset($data['registration_opens_at']) && $data['registration_opens_at'] !== ''
                ? new DateTimeImmutable($data['registration_opens_at'])
                : null,
            registrationClosesAt: isset($data['registration_closes_at']) && $data['registration_closes_at'] !== ''
                ? new DateTimeImmutable($data['registration_closes_at'])
                : null,
            cancellationDeadline: isset($data['cancellation_deadline']) && $data['cancellation_deadline'] !== ''
                ? new DateTimeImmutable($data['cancellation_deadline'])
                : null,
            requiresConfirmation: (bool) ($data['requires_confirmation'] ?? false),
            requiresPayment: (bool) ($data['requires_payment'] ?? false),
            membersOnly: (bool) ($data['members_only'] ?? false),
            customFields: $data['custom_fields'] ?? [],
            confirmationMessage: $data['confirmation_message'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'registration_enabled' => $this->registrationEnabled,
            'max_participants' => $this->maxParticipants,
            'waiting_list_enabled' => $this->waitingListEnabled,
            'max_waiting_list' => $this->maxWaitingList,
            'registration_opens_at' => $this->registrationOpensAt?->format('c'),
            'registration_closes_at' => $this->registrationClosesAt?->format('c'),
            'cancellation_deadline' => $this->cancellationDeadline?->format('c'),
            'requires_confirmation' => $this->requiresConfirmation,
            'requires_payment' => $this->requiresPayment,
            'members_only' => $this->membersOnly,
            'custom_fields' => $this->customFields,
            'confirmation_message' => $this->confirmationMessage,
        ];
    }
}
