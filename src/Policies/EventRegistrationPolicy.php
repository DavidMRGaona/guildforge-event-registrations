<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Policies;

use App\Infrastructure\Authorization\Policies\AuthorizesWithPermissions;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationModel;

final class EventRegistrationPolicy
{
    use AuthorizesWithPermissions;

    public function viewAny(UserModel $user): bool
    {
        return $this->authorize($user, 'event-registrations:registrations.view_any');
    }

    public function view(UserModel $user, EventRegistrationModel $registration): bool
    {
        // User can view their own registration or if they have the permission
        if ($user->id === $registration->user_id) {
            return true;
        }

        return $this->authorize($user, 'event-registrations:registrations.view');
    }

    public function create(UserModel $user): bool
    {
        return $this->authorize($user, 'event-registrations:registrations.create');
    }

    public function update(UserModel $user, EventRegistrationModel $registration): bool
    {
        return $this->authorize($user, 'event-registrations:registrations.update');
    }

    public function delete(UserModel $user, EventRegistrationModel $registration): bool
    {
        return $this->authorize($user, 'event-registrations:registrations.delete');
    }

    public function manageConfig(UserModel $user): bool
    {
        return $this->authorize($user, 'event-registrations:registrations.manage_config');
    }

    public function export(UserModel $user): bool
    {
        return $this->authorize($user, 'event-registrations:registrations.export');
    }
}
