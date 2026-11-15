<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Policies;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationModel;

/**
 * Who sees the list of the people registered in an event: the one place that says it.
 *
 * The list lives in the «Inscripciones» tab of the event's page in the panel, so seeing it takes what
 * opens that tab: access to the panel, the events resource, the event's edit page and the module's own
 * permission to list registrations, the permissions in TAB_PERMISSIONS. The tab asks for the module's
 * part (mayListRegistrations()) and the pages around it for the rest before it loads. Whatever shows
 * the same data elsewhere asks mayOpenEventTabs(), which restates the whole chain: the public profile,
 * and the bell notice that links to the tab (MANAGE_PERMISSIONS is those permissions plus the one to
 * change registrations), so none of them can drift apart.
 *
 * Every permission behind it is held by role, not by event: the policies do not read the event
 * (a test keeps that true), so the answer is one per person.
 */
final readonly class RegistrationListAccess
{
    /**
     * What opens the registrations tab of any event, as the permission keys the policies check.
     */
    public const array TAB_PERMISSIONS = [
        'admin.access',
        'events.view_any',
        'events.update',
        'event-registrations:registrations.view_any',
    ];

    /**
     * Managing is changing registrations and the notice of a new one links to the tab: whoever gets
     * it must be able to open that tab and to change what is in it.
     */
    public const array MANAGE_PERMISSIONS = [
        ...self::TAB_PERMISSIONS,
        'event-registrations:registrations.update',
    ];

    public function __construct(
        private Gate $gate,
        private AuthorizationServiceInterface $authorization,
    ) {}

    /**
     * The module's own part, the one the tab checks itself.
     */
    public function mayListRegistrations(?Authenticatable $user): bool
    {
        return $user instanceof UserModel
            && $this->gate->forUser($user)->allows('viewAny', EventRegistrationModel::class);
    }

    /**
     * Whether the person gets to the registrations tab of the events in the panel: the whole chain,
     * in one check.
     */
    public function mayOpenEventTabs(?Authenticatable $user): bool
    {
        return $user instanceof UserModel
            && $this->authorization->canAll($user, self::TAB_PERMISSIONS);
    }
}
