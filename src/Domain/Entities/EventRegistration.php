<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Domain\Entities;

use DateTimeImmutable;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Domain\Exceptions\CannotCancelRegistrationException;
use Modules\EventRegistrations\Domain\Exceptions\InvalidRegistrationTransitionException;
use Modules\EventRegistrations\Domain\ValueObjects\EventRegistrationId;

final class EventRegistration
{
    /**
     * @param  array<string, mixed>  $formData
     */
    public function __construct(
        private readonly EventRegistrationId $id,
        private readonly string $eventId,
        private readonly string $userId,
        private RegistrationState $state = RegistrationState::Pending,
        private ?int $position = null,
        private array $formData = [],
        private ?string $notes = null,
        private ?string $adminNotes = null,
        private ?DateTimeImmutable $confirmedAt = null,
        private ?DateTimeImmutable $cancelledAt = null,
        private readonly ?DateTimeImmutable $createdAt = null,
        private readonly ?DateTimeImmutable $updatedAt = null,
    ) {}

    public function id(): EventRegistrationId
    {
        return $this->id;
    }

    public function eventId(): string
    {
        return $this->eventId;
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function state(): RegistrationState
    {
        return $this->state;
    }

    public function position(): ?int
    {
        return $this->position;
    }

    /**
     * @return array<string, mixed>
     */
    public function formData(): array
    {
        return $this->formData;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    public function adminNotes(): ?string
    {
        return $this->adminNotes;
    }

    public function confirmedAt(): ?DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function cancelledAt(): ?DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function createdAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Confirm the registration: only a pending one or one on the waiting list.
     *
     * @throws InvalidRegistrationTransitionException
     */
    public function confirm(): void
    {
        if (! $this->state->canBeConfirmed()) {
            throw InvalidRegistrationTransitionException::cannotConfirm($this->id->value, $this->state);
        }

        $this->state = RegistrationState::Confirmed;
        $this->confirmedAt = new DateTimeImmutable;
        $this->position = null; // Clear position when confirmed
    }

    /**
     * Move to waiting list with a given position: only a pending or confirmed registration.
     *
     * @throws InvalidRegistrationTransitionException
     */
    public function moveToWaitingList(int $position): void
    {
        if (! $this->state->canMoveToWaitingList()) {
            throw InvalidRegistrationTransitionException::cannotMoveToWaitingList($this->id->value, $this->state);
        }

        $this->state = RegistrationState::WaitingList;
        $this->position = $position;
    }

    /**
     * Cancel the registration.
     *
     * @throws CannotCancelRegistrationException
     */
    public function cancel(): void
    {
        if ($this->state === RegistrationState::Cancelled) {
            throw CannotCancelRegistrationException::alreadyCancelled($this->id->value);
        }

        if ($this->state === RegistrationState::Rejected) {
            throw CannotCancelRegistrationException::registrationRejected($this->id->value);
        }

        $this->state = RegistrationState::Cancelled;
        $this->cancelledAt = new DateTimeImmutable;
    }

    /**
     * Reject the registration: anything but a cancelled or already rejected one.
     *
     * @throws InvalidRegistrationTransitionException
     */
    public function reject(): void
    {
        if (! $this->state->canBeRejected()) {
            throw InvalidRegistrationTransitionException::cannotReject($this->id->value, $this->state);
        }

        $this->state = RegistrationState::Rejected;
    }

    /**
     * Promote from waiting list to confirmed.
     */
    public function promoteFromWaitingList(): void
    {
        if ($this->state !== RegistrationState::WaitingList) {
            return;
        }

        $this->confirm();
    }

    /**
     * Update position in waiting list.
     */
    public function updatePosition(int $position): void
    {
        $this->position = $position;
    }

    /**
     * Update user notes.
     */
    public function updateNotes(?string $notes): void
    {
        $this->notes = $notes;
    }

    /**
     * Update admin notes.
     */
    public function updateAdminNotes(?string $adminNotes): void
    {
        $this->adminNotes = $adminNotes;
    }

    /**
     * Update form data.
     *
     * @param  array<string, mixed>  $formData
     */
    public function updateFormData(array $formData): void
    {
        $this->formData = $formData;
    }

    /**
     * Check if registration is active (confirmed).
     */
    public function isActive(): bool
    {
        return $this->state->isActive();
    }

    /**
     * Check if registration is on waiting list.
     */
    public function isOnWaitingList(): bool
    {
        return $this->state === RegistrationState::WaitingList;
    }

    /**
     * Check if registration can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return $this->state->canBeCancelled();
    }

    /**
     * Check if registration can be promoted from waiting list.
     */
    public function canBePromoted(): bool
    {
        return $this->state->canBePromoted();
    }

    /**
     * Reactivate a cancelled or rejected registration.
     *
     * @param  array<string, mixed>  $formData
     */
    public function reactivate(RegistrationState $newState, array $formData = [], ?string $notes = null): void
    {
        $this->state = $newState;
        $this->formData = $formData;
        $this->notes = $notes;
        $this->position = null;
        $this->confirmedAt = null;
        $this->cancelledAt = null;
    }
}
