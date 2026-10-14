<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Infrastructure\Services;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Domain\Enums\UserRole;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Modules\EventRegistrations\Application\Services\MembershipCheckerInterface;

/**
 * A member is a user holding the core `member` role.
 */
final readonly class RoleMembershipChecker implements MembershipCheckerInterface
{
    public function __construct(
        private AuthorizationServiceInterface $authorizationService,
    ) {}

    public function isMember(string $userId): bool
    {
        $user = UserModel::query()->find($userId);

        if ($user === null) {
            return false;
        }

        return $this->authorizationService->hasRole($user, UserRole::Member->value);
    }
}
