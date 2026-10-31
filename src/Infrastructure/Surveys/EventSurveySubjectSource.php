<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Infrastructure\Surveys;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Services\TimezoneServiceInterface;
use App\Application\Surveys\Contracts\SurveySubjectSourceInterface;
use App\Application\Surveys\DTOs\SurveyAudienceDTO;
use App\Application\Surveys\DTOs\SurveySubjectAttributeDTO;
use App\Application\Surveys\DTOs\SurveySubjectDTO;
use App\Application\Surveys\Enums\SurveySubjectStatus;
use App\Infrastructure\Authorization\Services\PermissionHolderFinder;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use Illuminate\Support\Str;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Domain\Services\Clock;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationConfigModel;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationModel;

/**
 * Events with registrations as survey subjects, through the core's contract: the surveys module,
 * when installed, asks it and nothing here depends on it. Events have no organizer of their own,
 * so their surveys are managed by whoever holds the module's survey permission; the confirmed
 * registrations are asked to answer.
 */
final readonly class EventSurveySubjectSource implements SurveySubjectSourceInterface
{
    /** Stored by surveys and their statistics: never changes. */
    public const string TYPE = 'event';

    public const string MANAGE_PERMISSION = 'registrations.manage_surveys';

    public function __construct(
        private SurveyableEvents $events,
        private Clock $clock,
        private TimezoneServiceInterface $timezone,
        private AuthorizationServiceInterface $authorization,
        private PermissionHolderFinder $holders,
    ) {}

    public function sourceType(): string
    {
        return self::TYPE;
    }

    public function sourceLabel(): string
    {
        return __('event-registrations::messages.surveys.source_label');
    }

    public function find(string $subjectId): ?SurveySubjectDTO
    {
        $event = $this->events->find($subjectId);
        if (! $event instanceof EventModel) {
            return null;
        }

        $endsAt = DateTimeImmutable::createFromInterface($event->end_date);
        $config = EventRegistrationConfigModel::query()->find($event->id);

        return new SurveySubjectDTO(
            sourceType: self::TYPE,
            subjectId: $event->id,
            title: $event->title,
            startsAt: DateTimeImmutable::createFromInterface($event->start_date),
            endsAt: $endsAt,
            organizerIds: array_values($this->holders->usersWhoCan(self::MANAGE_PERMISSION)->map(static fn (UserModel $user): string => (string) $user->id)->all()),
            attributes: [
                new SurveySubjectAttributeDTO('members_only', __('event-registrations::messages.surveys.attributes.members_only'), (bool) $config?->members_only),
            ],
            url: route('events.show', $event->slug),
            status: $endsAt <= $this->timezone->toStorage($this->clock->now()) ? SurveySubjectStatus::Ended : SurveySubjectStatus::Active,
        );
    }

    public function audience(string $subjectId): SurveyAudienceDTO
    {
        $event = $this->events->find($subjectId);
        if (! $event instanceof EventModel) {
            return SurveyAudienceDTO::empty();
        }

        $userIds = array_values(EventRegistrationModel::query()
            ->where('event_id', $event->id)
            ->where('state', RegistrationState::Confirmed->value)
            // An anonymised account keeps only a placeholder address
            ->whereHas('user', fn ($user) => $user->whereNull('anonymized_at'))
            ->orderBy('created_at')
            ->pluck('user_id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all());

        return new SurveyAudienceDTO(userIds: $userIds, guests: []);
    }

    public function canManage(string $userId, string $subjectId): bool
    {
        if (! Str::isUuid($userId) || ! $this->events->find($subjectId) instanceof EventModel) {
            return false;
        }

        $user = UserModel::query()->find($userId);

        return $user instanceof UserModel && $this->authorization->can($user, self::MANAGE_PERMISSION);
    }
}
