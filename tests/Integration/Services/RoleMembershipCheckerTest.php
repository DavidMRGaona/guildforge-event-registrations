<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Integration\Services;

use App\Domain\Enums\UserRole;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Modules\EventRegistrations\Application\Services\MembershipCheckerInterface;
use Modules\EventRegistrations\Infrastructure\Services\RoleMembershipChecker;
use Tests\Support\Modules\ModuleTestCase;

final class RoleMembershipCheckerTest extends ModuleTestCase
{
    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    public function test_the_checker_is_bound_to_the_role_based_implementation(): void
    {
        $this->assertInstanceOf(RoleMembershipChecker::class, $this->app->make(MembershipCheckerInterface::class));
    }

    public function test_a_user_with_the_member_role_is_a_member(): void
    {
        $user = UserModel::factory()->create();
        $role = RoleModel::firstOrCreate(['name' => 'member'], ['display_name' => 'Member']);
        $user->roles()->attach($role->id);

        $this->assertTrue($this->checker()->isMember((string) $user->id));
    }

    public function test_a_user_without_the_member_role_is_not_a_member(): void
    {
        $user = UserModel::factory()->create();

        $this->assertFalse($this->checker()->isMember((string) $user->id));
    }

    public function test_an_unknown_user_is_not_a_member(): void
    {
        $this->assertFalse($this->checker()->isMember(Str::uuid()->toString()));
    }

    public function test_the_legacy_role_column_does_not_make_a_user_a_member(): void
    {
        $user = UserModel::factory()->create(['role' => UserRole::Member]);
        $user->roles()->detach();

        $this->assertFalse($this->checker()->isMember((string) $user->id));
    }

    private function checker(): MembershipCheckerInterface
    {
        return $this->app->make(MembershipCheckerInterface::class);
    }
}
