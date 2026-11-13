<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Application\Proposals;

use App\Application\Proposals\Contracts\EventProposalRequestInterface;
use App\Application\Proposals\DTOs\ProposalRequestFieldDTO;
use App\Domain\Proposals\Exceptions\ProposalRequestRefusedException;
use App\Domain\ValueObjects\EventId;
use Modules\EventRegistrations\Domain\Entities\EventRegistrationConfig;
use Modules\EventRegistrations\Domain\Repositories\EventRegistrationConfigRepositoryInterface;

/**
 * Registrations asked for in an event proposal (core#581). Approving creates the event's
 * registration configuration from scratch; everything not asked here stays at the module's
 * defaults, for the administration to adjust in the registrations tab.
 */
final readonly class RegistrationsProposalRequest implements EventProposalRequestInterface
{
    public const string KEY = 'event-registrations.registrations';

    public const int MAX_CAPACITY = 10000;

    public function __construct(private EventRegistrationConfigRepositoryInterface $configs) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return __('event-registrations::messages.proposal_request.label');
    }

    public function question(): string
    {
        return __('event-registrations::messages.proposal_request.question');
    }

    public function order(): int
    {
        return 10;
    }

    public function fields(): array
    {
        return [
            ProposalRequestFieldDTO::number(
                'capacity',
                __('event-registrations::messages.proposal_request.capacity'),
                min: 1,
                max: self::MAX_CAPACITY,
                helperText: __('event-registrations::messages.proposal_request.capacity_help'),
            ),
            ProposalRequestFieldDTO::boolean(
                'waiting_list',
                __('event-registrations::messages.proposal_request.waiting_list'),
                default: true,
            )->visibleWhenFilled('capacity'),
        ];
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function apply(EventId $eventId, array $values): void
    {
        $id = $eventId->value;

        if ($this->configs->exists($id)) {
            throw ProposalRequestRefusedException::because(__('event-registrations::messages.proposal_request.already_configured'));
        }

        $capacity = $values['capacity'] ?? null;

        $this->configs->save(new EventRegistrationConfig(
            eventId: $id,
            registrationEnabled: true,
            maxParticipants: is_int($capacity) ? $capacity : null,
            waitingListEnabled: ($values['waiting_list'] ?? false) === true,
        ));
    }

    public function describe(array $values): string
    {
        $capacity = $values['capacity'] ?? null;

        if (! is_int($capacity)) {
            return __('event-registrations::messages.proposal_request.without_capacity');
        }

        return ($values['waiting_list'] ?? false) === true
            ? __('event-registrations::messages.proposal_request.with_capacity_and_waiting_list', ['capacity' => $capacity])
            : __('event-registrations::messages.proposal_request.with_capacity', ['capacity' => $capacity]);
    }
}
