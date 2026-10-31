<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Support;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationConfigModel;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationModel;

/**
 * Events offered as survey subjects (published, with registrations on), their registrations and
 * the people who manage their surveys.
 */
trait BuildsSurveyableEvents
{
    /**
     * @param  array<string, mixed>  $config
     */
    private function surveyableEvent(string $startsAt, string $endsAt, array $config = [], bool $published = true): EventModel
    {
        $event = EventModel::factory()->create([
            'title' => 'Evento de ejemplo',
            'start_date' => new DateTimeImmutable($startsAt),
            'end_date' => new DateTimeImmutable($endsAt),
            'is_published' => $published,
        ]);

        EventRegistrationConfigModel::create(['event_id' => $event->id, 'registration_enabled' => true, ...$config]);

        return $event;
    }

    private function registrant(EventModel $event, RegistrationState $state = RegistrationState::Confirmed): UserModel
    {
        $user = UserModel::factory()->create();

        EventRegistrationModel::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'state' => $state,
            'confirmed_at' => $state === RegistrationState::Confirmed ? new DateTimeImmutable('2030-01-01T00:00:00+00:00') : null,
        ]);

        return $user;
    }

    /**
     * An account whose only extra role holds exactly the given permission keys.
     */
    private function userWithPermission(string $key): UserModel
    {
        $role = RoleModel::create(['name' => 'tester_'.uniqid(), 'display_name' => 'Permissions tester']);
        $permission = PermissionModel::firstOrCreate(
            ['key' => $key],
            ['label' => $key, 'resource' => explode('.', $key)[0], 'action' => explode('.', $key)[1], 'module' => 'event-registrations'],
        );
        $role->permissions()->attach($permission->id);

        $user = UserModel::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }
}
