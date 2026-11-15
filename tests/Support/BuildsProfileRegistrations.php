<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Support;

use App\Application\Profiles\DTOs\ProfileOwnerDTO;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Domain\Services\Clock;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationModel;
use Tests\Support\Authorization\CreatesUsersWithPermissions;

/**
 * The world the profile section and stat tests share: a pinned clock, an owner, the people who may
 * or may not look at their registrations, and events in every state around "now".
 */
trait BuildsProfileRegistrations
{
    use CreatesUsersWithPermissions;

    /** The pinned "now" of these tests, in UTC. */
    protected const string NOW = '2030-06-15 12:00:00';

    protected const string VIEW = 'event-registrations:registrations.view';

    protected const string VIEW_ANY = 'event-registrations:registrations.view_any';

    protected UserModel $owner;

    protected function pinTheClock(): void
    {
        $this->useUtcStorage();
        $this->app->instance(Clock::class, FixedClock::at(self::NOW.' UTC'));
        $this->owner = UserModel::factory()->create();
    }

    protected function ownerDto(): ProfileOwnerDTO
    {
        return new ProfileOwnerDTO($this->owner->id, 'ana-garcia', 'Ana García');
    }

    /**
     * Who looks at the profile, by name: null is a guest. The list of the people registered in an event
     * is in the panel's tab, so what opens that tab (the panel, the events, the edit page and the
     * registrations list) is what lets somebody else see the owner's events.
     *
     * @return array<string, ?Authenticatable>
     */
    protected function viewers(): array
    {
        $tab = ['events.view_any', 'events.update', self::VIEW_ANY];

        return [
            'guest' => null,
            'owner' => $this->owner,
            'admin' => UserModel::factory()->admin()->create(),
            'staff who open the tab' => $this->editorWithPermissions($tab),
            'staff who open the tab, plus view' => $this->editorWithPermissions([...$tab, self::VIEW]),
            'staff with only registrations.view' => $this->editorWithPermissions([self::VIEW]),
            'staff who can only list registrations' => $this->editorWithPermissions([self::VIEW_ANY]),
            'staff without the events resource' => $this->editorWithPermissions(['events.update', self::VIEW_ANY]),
            'staff who cannot edit the event' => $this->editorWithPermissions(['events.view_any', 'events.view', self::VIEW_ANY]),
            'the tab permissions without the panel' => $this->memberWithPermissions($tab),
            'another member' => UserModel::factory()->create(),
        ];
    }

    /**
     * An account with a role holding the permissions but no access to the panel.
     *
     * @param  list<string>  $permissionKeys
     */
    protected function memberWithPermissions(array $permissionKeys): UserModel
    {
        $role = RoleModel::create(['name' => 'tester-'.uniqid(), 'display_name' => 'Permissions tester']);

        foreach ($permissionKeys as $key) {
            [$resource, $action] = explode('.', $key, 2);
            $permission = PermissionModel::firstOrCreate(
                ['key' => $key],
                ['label' => $key, 'resource' => $resource, 'action' => $action],
            );
            $role->permissions()->attach($permission->id);
        }

        $user = UserModel::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function eventAt(string $start, string $end, array $attributes = []): EventModel
    {
        return EventModel::factory()->published()->create([
            'start_date' => new \DateTimeImmutable($start.' UTC'),
            'end_date' => new \DateTimeImmutable($end.' UTC'),
            ...$attributes,
        ]);
    }

    protected function upcomingEvent(string $title = 'Jornada de rol', int $daysAhead = 10): EventModel
    {
        $start = (new \DateTimeImmutable(self::NOW.' UTC'))->modify("+{$daysAhead} days");

        return $this->eventAt($start->format('Y-m-d H:i:s'), $start->modify('+4 hours')->format('Y-m-d H:i:s'), ['title' => $title]);
    }

    protected function pastEvent(string $title = 'Torneo pasado', int $daysAgo = 10): EventModel
    {
        $start = (new \DateTimeImmutable(self::NOW.' UTC'))->modify("-{$daysAgo} days");

        return $this->eventAt($start->format('Y-m-d H:i:s'), $start->modify('+4 hours')->format('Y-m-d H:i:s'), ['title' => $title]);
    }

    protected function register(EventModel $event, RegistrationState $state = RegistrationState::Confirmed, ?UserModel $user = null): EventRegistrationModel
    {
        return EventRegistrationModel::create([
            'event_id' => $event->id,
            'user_id' => ($user ?? $this->owner)->id,
            'state' => $state,
            'notes' => 'Vegetarian, arrives late',
            'admin_notes' => 'Internal: pays at the door',
            'form_data' => ['phone' => '600 000 000'],
        ]);
    }
}
