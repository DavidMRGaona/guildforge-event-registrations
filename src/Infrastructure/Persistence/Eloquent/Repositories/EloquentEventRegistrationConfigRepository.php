<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Repositories;

use DateTimeImmutable;
use Modules\EventRegistrations\Domain\Entities\EventRegistrationConfig;
use Modules\EventRegistrations\Domain\Repositories\EventRegistrationConfigRepositoryInterface;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationConfigModel;

final readonly class EloquentEventRegistrationConfigRepository implements EventRegistrationConfigRepositoryInterface
{
    public function save(EventRegistrationConfig $config): void
    {
        EventRegistrationConfigModel::query()->updateOrCreate(
            ['event_id' => $config->eventId()],
            [
                'registration_enabled' => $config->isRegistrationEnabled(),
                'max_participants' => $config->maxParticipants(),
                'waiting_list_enabled' => $config->isWaitingListEnabled(),
                'max_waiting_list' => $config->maxWaitingList(),
                'registration_opens_at' => $config->registrationOpensAt(),
                'registration_closes_at' => $config->registrationClosesAt(),
                'cancellation_deadline' => $config->cancellationDeadline(),
                'requires_confirmation' => $config->requiresConfirmation(),
                'requires_payment' => $config->requiresPayment(),
                'members_only' => $config->isMembersOnly(),
                'custom_fields' => $config->customFields(),
                'confirmation_message' => $config->confirmationMessage(),
            ]
        );
    }

    public function findByEvent(string $eventId): ?EventRegistrationConfig
    {
        $model = EventRegistrationConfigModel::query()->find($eventId);

        return $model !== null ? $this->toEntity($model) : null;
    }

    public function findByEventOrDefault(string $eventId): EventRegistrationConfig
    {
        $config = $this->findByEvent($eventId);

        if ($config !== null) {
            return $config;
        }

        // Return default config with module settings (disabled by default)
        $maxParticipants = config('modules.settings.event-registrations.default_max_participants');
        $maxWaitingList = config('modules.settings.event-registrations.default_max_waiting_list');

        return new EventRegistrationConfig(
            eventId: $eventId,
            registrationEnabled: (bool) config('modules.settings.event-registrations.default_registration_enabled', false),
            maxParticipants: $maxParticipants !== null ? (int) $maxParticipants : null,
            waitingListEnabled: (bool) config('modules.settings.event-registrations.default_waiting_list_enabled', true),
            maxWaitingList: $maxWaitingList !== null ? (int) $maxWaitingList : null,
            requiresConfirmation: (bool) config('modules.settings.event-registrations.default_requires_confirmation', false),
        );
    }

    public function delete(string $eventId): void
    {
        EventRegistrationConfigModel::query()
            ->where('event_id', $eventId)
            ->delete();
    }

    public function exists(string $eventId): bool
    {
        return EventRegistrationConfigModel::query()
            ->where('event_id', $eventId)
            ->exists();
    }

    private function toEntity(EventRegistrationConfigModel $model): EventRegistrationConfig
    {
        return new EventRegistrationConfig(
            eventId: $model->event_id,
            registrationEnabled: $model->registration_enabled,
            maxParticipants: $model->max_participants,
            waitingListEnabled: $model->waiting_list_enabled,
            maxWaitingList: $model->max_waiting_list,
            registrationOpensAt: $model->registration_opens_at !== null
                ? DateTimeImmutable::createFromMutable($model->registration_opens_at->toDateTime())
                : null,
            registrationClosesAt: $model->registration_closes_at !== null
                ? DateTimeImmutable::createFromMutable($model->registration_closes_at->toDateTime())
                : null,
            cancellationDeadline: $model->cancellation_deadline !== null
                ? DateTimeImmutable::createFromMutable($model->cancellation_deadline->toDateTime())
                : null,
            requiresConfirmation: $model->requires_confirmation,
            requiresPayment: $model->requires_payment,
            membersOnly: $model->members_only,
            customFields: $model->custom_fields ?? [],
            confirmationMessage: $model->confirmation_message,
            createdAt: $model->created_at !== null
                ? DateTimeImmutable::createFromMutable($model->created_at->toDateTime())
                : null,
            updatedAt: $model->updated_at !== null
                ? DateTimeImmutable::createFromMutable($model->updated_at->toDateTime())
                : null,
        );
    }
}
