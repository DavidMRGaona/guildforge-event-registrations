<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Infrastructure\Services;

use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Modules\EventRegistrations\Application\Services\MembershipCheckerInterface;

/**
 * A member is an association member: a user holding the role configured in the core site
 * settings ("socio" by default), never the "member" role every registered user has.
 * Admins get no exception.
 */
final readonly class AssociationMembershipChecker implements MembershipCheckerInterface
{
    public function __construct(
        private AssociationMembershipServiceInterface $associationMembership,
    ) {}

    public function isMember(string $userId): bool
    {
        $user = UserModel::query()->find($userId);

        if ($user === null) {
            return false;
        }

        return $this->associationMembership->isAssociationMember($user);
    }
}
