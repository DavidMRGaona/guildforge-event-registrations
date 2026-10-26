<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Application\DTOs;

use App\Application\Services\TimezoneServiceInterface;
use DateTimeImmutable;
use Modules\EventRegistrations\Domain\Entities\EventRegistrationConfig;
use Modules\EventRegistrations\Domain\Enums\CancellationClosedReason;

final readonly class EventRegistrationConfigDTO
{
    /**
     * @param  array<array{name: string, label: string, type: string, required: bool, options?: array<string>}>  $customFields
     */
    public function __construct(
        public string $eventId,
        public bool $registrationEnabled,
        public ?int $maxParticipants,
        public bool $waitingListEnabled,
        public ?int $maxWaitingList,
        public ?DateTimeImmutable $registrationOpensAt,
        public ?DateTimeImmutable $registrationClosesAt,
        public ?DateTimeImmutable $cancellationDeadline,
        public bool $requiresConfirmation,
        public bool $requiresPayment,
        public bool $membersOnly,
        public array $customFields,
        public ?string $confirmationMessage,
        // Computed fields for frontend
        public int $currentParticipants,
        public int $currentWaitingList,
        public int $availableSpots,
        public bool $isOpen,
        public bool $isFull,
        public bool $canCancel,
        public ?CancellationClosedReason $cancellationClosedReason,
    ) {}

    public static function fromEntity(
        EventRegistrationConfig $config,
        int $currentParticipants,
        int $currentWaitingList,
        DateTimeImmutable $now,
    ): self {
        $maxParticipants = $config->maxParticipants();
        $availableSpots = $maxParticipants !== null
            ? max(0, $maxParticipants - $currentParticipants)
            : PHP_INT_MAX;

        $isFull = $maxParticipants !== null && $currentParticipants >= $maxParticipants;

        return new self(
            eventId: $config->eventId(),
            registrationEnabled: $config->isRegistrationEnabled(),
            maxParticipants: $config->maxParticipants(),
            waitingListEnabled: $config->isWaitingListEnabled(),
            maxWaitingList: $config->maxWaitingList(),
            registrationOpensAt: $config->registrationOpensAt(),
            registrationClosesAt: $config->registrationClosesAt(),
            cancellationDeadline: $config->cancellationDeadline(),
            requiresConfirmation: $config->requiresConfirmation(),
            requiresPayment: $config->requiresPayment(),
            membersOnly: $config->isMembersOnly(),
            customFields: $config->customFields(),
            confirmationMessage: $config->confirmationMessage(),
            currentParticipants: $currentParticipants,
            currentWaitingList: $currentWaitingList,
            availableSpots: $availableSpots === PHP_INT_MAX ? -1 : $availableSpots,
            isOpen: $config->isOpen($now),
            isFull: $isFull,
            canCancel: $config->canCancelNow($now),
            cancellationClosedReason: $config->cancellationClosedReason($now),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $timezone = app(TimezoneServiceInterface::class);

        return [
            'event_id' => $this->eventId,
            'registration_enabled' => $this->registrationEnabled,
            'max_participants' => $this->maxParticipants,
            'waiting_list_enabled' => $this->waitingListEnabled,
            'max_waiting_list' => $this->maxWaitingList,
            'registration_opens_at' => $timezone->iso($this->registrationOpensAt),
            'registration_closes_at' => $timezone->iso($this->registrationClosesAt),
            'cancellation_deadline' => $timezone->iso($this->cancellationDeadline),
            'requires_confirmation' => $this->requiresConfirmation,
            'requires_payment' => $this->requiresPayment,
            'members_only' => $this->membersOnly,
            'custom_fields' => $this->customFields,
            'confirmation_message' => $this->confirmationMessage,
            'current_participants' => $this->currentParticipants,
            'current_waiting_list' => $this->currentWaitingList,
            'available_spots' => $this->availableSpots,
            'is_open' => $this->isOpen,
            'is_full' => $this->isFull,
            'can_cancel' => $this->canCancel,
            'cancellation_closed_reason' => $this->cancellationClosedReason?->value,
        ];
    }
}
