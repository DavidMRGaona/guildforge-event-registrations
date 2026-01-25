<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Domain\Enums;

enum RegistrationState: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case WaitingList = 'waiting_list';
    case Cancelled = 'cancelled';
    case Rejected = 'rejected';

    /**
     * Get human-readable label for the state.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => __('event-registrations::messages.states.pending'),
            self::Confirmed => __('event-registrations::messages.states.confirmed'),
            self::WaitingList => __('event-registrations::messages.states.waiting_list'),
            self::Cancelled => __('event-registrations::messages.states.cancelled'),
            self::Rejected => __('event-registrations::messages.states.rejected'),
        };
    }

    /**
     * Get badge color for Filament UI.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Confirmed => 'success',
            self::WaitingList => 'info',
            self::Cancelled => 'gray',
            self::Rejected => 'danger',
        };
    }

    /**
     * Check if registration is in an active state (can participate).
     */
    public function isActive(): bool
    {
        return $this === self::Confirmed;
    }

    /**
     * Check if registration is in a waiting state.
     */
    public function isWaiting(): bool
    {
        return $this === self::WaitingList || $this === self::Pending;
    }

    /**
     * Check if registration is in a final state (cannot be changed by user).
     */
    public function isFinal(): bool
    {
        return $this === self::Cancelled || $this === self::Rejected;
    }

    /**
     * Check if registration can be cancelled by the user.
     */
    public function canBeCancelled(): bool
    {
        return ! $this->isFinal();
    }

    /**
     * Check if registration can be promoted from waiting list.
     */
    public function canBePromoted(): bool
    {
        return $this === self::WaitingList;
    }

    /**
     * Get all state values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get options for form select fields.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
