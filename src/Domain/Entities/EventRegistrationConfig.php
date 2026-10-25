<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Domain\Entities;

use DateTimeImmutable;

final class EventRegistrationConfig
{
    /**
     * @param  array<array{name: string, label: string, type: string, required: bool, options?: array<string>}>  $customFields
     */
    public function __construct(
        private readonly string $eventId,
        private bool $registrationEnabled = true,
        private ?int $maxParticipants = null,
        private bool $waitingListEnabled = true,
        private ?int $maxWaitingList = null,
        private ?DateTimeImmutable $registrationOpensAt = null,
        private ?DateTimeImmutable $registrationClosesAt = null,
        private ?DateTimeImmutable $cancellationDeadline = null,
        private bool $requiresConfirmation = false,
        private bool $requiresPayment = false,
        private bool $membersOnly = false,
        private array $customFields = [],
        private ?string $confirmationMessage = null,
        private ?string $notificationEmail = null,
        private readonly ?DateTimeImmutable $createdAt = null,
        private readonly ?DateTimeImmutable $updatedAt = null,
        private readonly ?DateTimeImmutable $eventStartsAt = null,
    ) {}

    public function eventId(): string
    {
        return $this->eventId;
    }

    public function isRegistrationEnabled(): bool
    {
        return $this->registrationEnabled;
    }

    public function maxParticipants(): ?int
    {
        return $this->maxParticipants;
    }

    public function isWaitingListEnabled(): bool
    {
        return $this->waitingListEnabled;
    }

    public function maxWaitingList(): ?int
    {
        return $this->maxWaitingList;
    }

    public function registrationOpensAt(): ?DateTimeImmutable
    {
        return $this->registrationOpensAt;
    }

    public function registrationClosesAt(): ?DateTimeImmutable
    {
        return $this->registrationClosesAt;
    }

    public function cancellationDeadline(): ?DateTimeImmutable
    {
        return $this->cancellationDeadline;
    }

    public function requiresConfirmation(): bool
    {
        return $this->requiresConfirmation;
    }

    public function requiresPayment(): bool
    {
        return $this->requiresPayment;
    }

    public function isMembersOnly(): bool
    {
        return $this->membersOnly;
    }

    /**
     * @return array<array{name: string, label: string, type: string, required: bool, options?: array<string>}>
     */
    public function customFields(): array
    {
        return $this->customFields;
    }

    public function confirmationMessage(): ?string
    {
        return $this->confirmationMessage;
    }

    public function notificationEmail(): ?string
    {
        return $this->notificationEmail;
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
     * Start of the event, read from the core event; not persisted with the config. Null when
     * the event does not exist.
     */
    public function eventStartsAt(): ?DateTimeImmutable
    {
        return $this->eventStartsAt;
    }

    /**
     * Whether the event has started (from its start time on). An event whose start is unknown
     * counts as started, so nothing is accepted for it.
     */
    public function hasEventStarted(?DateTimeImmutable $now = null): bool
    {
        $now ??= new DateTimeImmutable;

        return ! $this->eventStartsAt instanceof DateTimeImmutable || $now >= $this->eventStartsAt;
    }

    /**
     * Check if registration is currently open: enabled, within the configured dates and before
     * the event starts (the start closes it whatever the close date says).
     */
    public function isOpen(?DateTimeImmutable $now = null): bool
    {
        $now ??= new DateTimeImmutable;

        if (! $this->registrationEnabled) {
            return false;
        }

        if ($this->hasEventStarted($now)) {
            return false;
        }

        if ($this->registrationOpensAt instanceof DateTimeImmutable && $now < $this->registrationOpensAt) {
            return false;
        }

        return ! $this->registrationClosesAt instanceof DateTimeImmutable || $now <= $this->registrationClosesAt;
    }

    /**
     * Check if the event has a participant limit.
     */
    public function hasParticipantLimit(): bool
    {
        return $this->maxParticipants !== null && $this->maxParticipants > 0;
    }

    /**
     * Check if the event has a waiting list limit.
     */
    public function hasWaitingListLimit(): bool
    {
        return $this->maxWaitingList !== null && $this->maxWaitingList > 0;
    }

    /**
     * Check if the user may still cancel: before the deadline, if any, and before the event
     * starts (without a deadline the start is the cut).
     */
    public function canCancelNow(?DateTimeImmutable $now = null): bool
    {
        $now ??= new DateTimeImmutable;

        if ($this->hasEventStarted($now)) {
            return false;
        }

        if (! $this->cancellationDeadline instanceof DateTimeImmutable) {
            return true;
        }

        return $now <= $this->cancellationDeadline;
    }

    /**
     * Update registration enabled state.
     */
    public function setRegistrationEnabled(bool $enabled): void
    {
        $this->registrationEnabled = $enabled;
    }

    /**
     * Update max participants limit.
     */
    public function setMaxParticipants(?int $max): void
    {
        $this->maxParticipants = $max;
    }

    /**
     * Update waiting list enabled state.
     */
    public function setWaitingListEnabled(bool $enabled): void
    {
        $this->waitingListEnabled = $enabled;
    }

    /**
     * Update max waiting list limit.
     */
    public function setMaxWaitingList(?int $max): void
    {
        $this->maxWaitingList = $max;
    }

    /**
     * Update registration open date.
     */
    public function setRegistrationOpensAt(?DateTimeImmutable $date): void
    {
        $this->registrationOpensAt = $date;
    }

    /**
     * Update registration close date.
     */
    public function setRegistrationClosesAt(?DateTimeImmutable $date): void
    {
        $this->registrationClosesAt = $date;
    }

    /**
     * Update cancellation deadline.
     */
    public function setCancellationDeadline(?DateTimeImmutable $date): void
    {
        $this->cancellationDeadline = $date;
    }

    /**
     * Update requires confirmation state.
     */
    public function setRequiresConfirmation(bool $requires): void
    {
        $this->requiresConfirmation = $requires;
    }

    /**
     * Update requires payment state.
     */
    public function setRequiresPayment(bool $requires): void
    {
        $this->requiresPayment = $requires;
    }

    /**
     * Update members only state.
     */
    public function setMembersOnly(bool $membersOnly): void
    {
        $this->membersOnly = $membersOnly;
    }

    /**
     * Update custom fields.
     *
     * @param  array<array{name: string, label: string, type: string, required: bool, options?: array<string>}>  $fields
     */
    public function setCustomFields(array $fields): void
    {
        $this->customFields = $fields;
    }

    /**
     * Update confirmation message.
     */
    public function setConfirmationMessage(?string $message): void
    {
        $this->confirmationMessage = $message;
    }

    /**
     * Update notification email.
     */
    public function setNotificationEmail(?string $email): void
    {
        $this->notificationEmail = $email;
    }
}
