<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Integration\Profiles;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Profiles\Services\ProfileRendererInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Mockery;
use Modules\EventRegistrations\Domain\Services\Clock;
use Modules\EventRegistrations\Policies\RegistrationListAccess;
use Modules\EventRegistrations\Tests\Support\BuildsProfileRegistrations;
use RuntimeException;
use Tests\Support\Modules\ModuleTestCase;

/**
 * When the rule or the query behind the section and the stat fails for somebody who is not the owner,
 * the profile is drawn without them instead of breaking or showing more than it should.
 */
final class ProfileFailsClosedTest extends ModuleTestCase
{
    use BuildsProfileRegistrations;

    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    private UserModel $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pinTheClock();
        $this->register($this->upcomingEvent());
        $this->register($this->pastEvent());
        $this->staff = $this->editorWithPermissions(['events.view_any', 'events.update', self::VIEW_ANY]);
    }

    public function test_the_staff_who_open_the_tab_see_both_when_nothing_fails(): void
    {
        $profile = $this->app->make(ProfileRendererInterface::class)->render($this->ownerDto(), $this->staff);

        $this->assertCount(1, $profile->aside);
        $this->assertCount(1, $profile->stats);
    }

    public function test_a_failing_permission_check_leaves_out_the_section_and_the_stat(): void
    {
        $authorization = Mockery::mock(AuthorizationServiceInterface::class)->shouldIgnoreMissing();
        $authorization->shouldReceive('canAll')
            ->with(Mockery::any(), RegistrationListAccess::TAB_PERMISSIONS)
            ->andThrow(new RuntimeException('the check failed'));
        $this->app->instance(AuthorizationServiceInterface::class, $authorization);
        $this->app->forgetScopedInstances();

        $profile = $this->app->make(ProfileRendererInterface::class)->render($this->ownerDto(), $this->staff);

        $this->assertSame([], $profile->aside);
        $this->assertSame([], $profile->stats);
        $this->assertSame('ana-garcia', $profile->header->handle);
    }

    public function test_a_failing_query_leaves_out_the_section_and_the_stat(): void
    {
        // Both read "now" from the clock to tell upcoming from attended
        $this->app->bind(Clock::class, static function (): never {
            throw new RuntimeException('the clock failed');
        });
        $this->app->forgetScopedInstances();

        $profile = $this->app->make(ProfileRendererInterface::class)->render($this->ownerDto(), $this->staff);

        $this->assertSame([], $profile->aside);
        $this->assertSame([], $profile->stats);
    }
}
