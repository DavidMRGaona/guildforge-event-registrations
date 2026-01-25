<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Repositories;

use DateTimeImmutable;
use Modules\EventRegistrations\Domain\Entities\EventRegistration;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Domain\Exceptions\RegistrationNotFoundException;
use Modules\EventRegistrations\Domain\Repositories\EventRegistrationRepositoryInterface;
use Modules\EventRegistrations\Domain\ValueObjects\EventRegistrationId;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationModel;

final readonly class EloquentEventRegistrationRepository implements EventRegistrationRepositoryInterface
{
    public function save(EventRegistration $registration): void
    {
        EventRegistrationModel::query()->updateOrCreate(
            ['id' => $registration->id()->value],
            [
                'event_id' => $registration->eventId(),
                'user_id' => $registration->userId(),
                'state' => $registration->state()->value,
                'position' => $registration->position(),
                'form_data' => $registration->formData(),
                'notes' => $registration->notes(),
                'admin_notes' => $registration->adminNotes(),
                'confirmed_at' => $registration->confirmedAt(),
                'cancelled_at' => $registration->cancelledAt(),
            ]
        );
    }

    public function find(EventRegistrationId $id): ?EventRegistration
    {
        $model = EventRegistrationModel::query()->find($id->value);

        return $model !== null ? $this->toEntity($model) : null;
    }

    public function findOrFail(EventRegistrationId $id): EventRegistration
    {
        $registration = $this->find($id);

        if ($registration === null) {
            throw RegistrationNotFoundException::withId($id->value);
        }

        return $registration;
    }

    public function findByUserAndEvent(string $userId, string $eventId): ?EventRegistration
    {
        $model = EventRegistrationModel::query()
            ->where('user_id', $userId)
            ->where('event_id', $eventId)
            ->first();

        return $model !== null ? $this->toEntity($model) : null;
    }

    public function delete(EventRegistrationId $id): void
    {
        EventRegistrationModel::query()
            ->where('id', $id->value)
            ->delete();
    }

    /**
     * @return array<EventRegistration>
     */
    public function findByEvent(string $eventId): array
    {
        return EventRegistrationModel::query()
            ->where('event_id', $eventId)
            ->orderBy('created_at')
            ->get()
            ->map(fn (EventRegistrationModel $model) => $this->toEntity($model))
            ->all();
    }

    /**
     * @return array<EventRegistration>
     */
    public function findByEventAndState(string $eventId, RegistrationState $state): array
    {
        return EventRegistrationModel::query()
            ->where('event_id', $eventId)
            ->where('state', $state->value)
            ->orderBy('created_at')
            ->get()
            ->map(fn (EventRegistrationModel $model) => $this->toEntity($model))
            ->all();
    }

    /**
     * @return array<EventRegistration>
     */
    public function findByUser(string $userId): array
    {
        return EventRegistrationModel::query()
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (EventRegistrationModel $model) => $this->toEntity($model))
            ->all();
    }

    public function countConfirmedByEvent(string $eventId): int
    {
        return EventRegistrationModel::query()
            ->where('event_id', $eventId)
            ->where('state', RegistrationState::Confirmed->value)
            ->count();
    }

    public function countWaitingListByEvent(string $eventId): int
    {
        return EventRegistrationModel::query()
            ->where('event_id', $eventId)
            ->where('state', RegistrationState::WaitingList->value)
            ->count();
    }

    public function getNextWaitingListPosition(string $eventId): int
    {
        $maxPosition = EventRegistrationModel::query()
            ->where('event_id', $eventId)
            ->where('state', RegistrationState::WaitingList->value)
            ->max('position');

        return ($maxPosition ?? 0) + 1;
    }

    public function findFirstInWaitingList(string $eventId): ?EventRegistration
    {
        $model = EventRegistrationModel::query()
            ->where('event_id', $eventId)
            ->where('state', RegistrationState::WaitingList->value)
            ->orderBy('position')
            ->first();

        return $model !== null ? $this->toEntity($model) : null;
    }

    /**
     * @return array<EventRegistration>
     */
    public function findWaitingListOrdered(string $eventId): array
    {
        return EventRegistrationModel::query()
            ->where('event_id', $eventId)
            ->where('state', RegistrationState::WaitingList->value)
            ->orderBy('position')
            ->get()
            ->map(fn (EventRegistrationModel $model) => $this->toEntity($model))
            ->all();
    }

    private function toEntity(EventRegistrationModel $model): EventRegistration
    {
        return new EventRegistration(
            id: EventRegistrationId::fromString((string) $model->id),
            eventId: (string) $model->event_id,
            userId: (string) $model->user_id,
            state: $model->state,
            position: $model->position,
            formData: $model->form_data ?? [],
            notes: $model->notes,
            adminNotes: $model->admin_notes,
            confirmedAt: $model->confirmed_at !== null
                ? DateTimeImmutable::createFromMutable($model->confirmed_at->toDateTime())
                : null,
            cancelledAt: $model->cancelled_at !== null
                ? DateTimeImmutable::createFromMutable($model->cancelled_at->toDateTime())
                : null,
            createdAt: $model->created_at !== null
                ? DateTimeImmutable::createFromMutable($model->created_at->toDateTime())
                : null,
            updatedAt: $model->updated_at !== null
                ? DateTimeImmutable::createFromMutable($model->updated_at->toDateTime())
                : null,
        );
    }
}
