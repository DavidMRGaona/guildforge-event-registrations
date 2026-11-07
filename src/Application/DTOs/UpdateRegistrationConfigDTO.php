<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Application\DTOs;

use App\Application\Services\TimezoneServiceInterface;
use DateTimeImmutable;

/**
 * A change to the registration config of an event. Only the fields it provides are changed:
 * a form sends the fields it shows (Filament leaves hidden ones out), and saving it must not
 * blank the others.
 */
final readonly class UpdateRegistrationConfigDTO
{
    /**
     * Every field of the config that can be changed, by its form key.
     *
     * @var list<string>
     */
    public const array FIELDS = [
        'registration_enabled',
        'max_participants',
        'waiting_list_enabled',
        'max_waiting_list',
        'registration_opens_at',
        'registration_closes_at',
        'cancellation_deadline',
        'requires_confirmation',
        'requires_payment',
        'members_only',
        'custom_fields',
        'confirmation_message',
        'notification_email',
    ];

    /**
     * @param  array<array{name: string, label: string, type: string, required: bool, options?: array<string>}>  $customFields
     * @param  list<string>|null  $fields  the fields to change; null changes them all
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
        public ?string $notificationEmail = null,
        public ?array $fields = null,
    ) {}

    /**
     * Whether this change sets the field (by its form key).
     */
    public function provides(string $field): bool
    {
        return in_array($field, $this->fields ?? self::FIELDS, true);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $maxParticipants = $data['max_participants'] ?? null;
        $maxWaitingList = $data['max_waiting_list'] ?? null;
        $notificationEmail = $data['notification_email'] ?? null;

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
            // An emptied field clears the address: no notices are sent then
            notificationEmail: is_string($notificationEmail) && trim($notificationEmail) !== '' ? trim($notificationEmail) : null,
            fields: array_values(array_intersect(self::FIELDS, array_keys($data))),
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
            'notification_email' => $this->notificationEmail,
        ];
    }
}
