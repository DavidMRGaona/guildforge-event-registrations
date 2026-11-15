<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Integration\Profiles;

use App\Application\Profiles\DTOs\ProfileStatDTO;
use App\Application\Profiles\Services\ProfileRendererInterface;
use App\Application\Profiles\Services\ProfileStatRegistryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Infrastructure\Profiles\AttendedEventsProfileStat;
use Modules\EventRegistrations\Tests\Support\BuildsProfileRegistrations;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Modules\ModuleTestCase;

/**
 * «Eventos asistidos» on a public profile: the published events that ended with the owner confirmed
 * in them, counted only for the people the module lets see that person's registrations.
 */
final class AttendedEventsProfileStatTest extends ModuleTestCase
{
    use BuildsProfileRegistrations;

    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pinTheClock();
    }

    public function test_the_module_offers_the_stat(): void
    {
        $registration = $this->app->make(ProfileStatRegistryInterface::class)->find('event-registrations.attended');

        $this->assertNotNull($registration);
        $this->assertSame('event-registrations', $registration->module);
        $this->assertSame(AttendedEventsProfileStat::class, $registration->class);

        $stat = $this->stat();
        $this->assertTrue($stat->enabledByDefault());
        $this->assertTrue($stat->isAvailable());
        $this->assertNotSame('', $stat->label());
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function whoSees(): array
    {
        return UpcomingEventsProfileSectionTest::whoSees();
    }

    #[DataProvider('whoSees')]
    public function test_only_who_may_see_the_registrations_gets_the_count_and_the_rest_get_null(string $who, bool $sees): void
    {
        $this->register($this->pastEvent());

        $stat = $this->ask($this->viewers()[$who]);

        if ($sees) {
            $this->assertSame(1, $stat?->value);

            return;
        }

        $this->assertNull($stat);
    }

    public function test_a_viewer_who_may_not_look_never_gets_a_zero_that_hides_the_truth(): void
    {
        // The owner attended nothing, and a guest still learns nothing: null, not 0
        $this->assertNull($this->ask(null));
        $this->assertNull($this->ask(UserModel::factory()->create()));
    }

    public function test_it_counts_confirmed_registrations_in_published_events_that_ended(): void
    {
        $this->register($this->pastEvent('Uno', 30));
        $this->register($this->pastEvent('Dos', 20));
        $this->register($this->eventAt('2030-06-15 08:00:00', '2030-06-15 11:59:59', ['title' => 'Terminó hace un segundo']));

        $this->assertSame(3, $this->ask($this->owner)?->value);
    }

    public function test_events_still_to_come_or_in_progress_do_not_count(): void
    {
        $this->register($this->pastEvent());
        $this->register($this->upcomingEvent());
        $this->register($this->eventAt('2030-06-15 09:00:00', '2030-06-15 12:00:00', ['title' => 'Termina ahora']));
        $this->register($this->eventAt('2030-06-15 09:00:00', '2030-06-15 18:00:00', ['title' => 'En curso']));

        $this->assertSame(1, $this->ask($this->owner)?->value);
    }

    public function test_registrations_that_were_not_confirmed_do_not_count(): void
    {
        foreach ([RegistrationState::Pending, RegistrationState::WaitingList, RegistrationState::Rejected, RegistrationState::Cancelled] as $state) {
            $this->register($this->pastEvent("Pasado {$state->value}"), $state);
        }
        $this->register($this->pastEvent('Confirmado'));

        $this->assertSame(1, $this->ask($this->owner)?->value);
    }

    public function test_events_that_are_not_published_do_not_count(): void
    {
        $this->register($this->pastEvent('Publicado'));
        $draft = $this->pastEvent('Borrador');
        $draft->forceFill(['is_published' => false])->save();
        $this->register($draft);

        $this->assertSame(1, $this->ask($this->owner)?->value);
    }

    public function test_other_peoples_registrations_do_not_count(): void
    {
        $this->register($this->pastEvent('De otra persona'), RegistrationState::Confirmed, UserModel::factory()->create());
        $this->register($this->pastEvent('Mía'));

        $this->assertSame(1, $this->ask($this->owner)?->value);
    }

    public function test_with_nothing_attended_there_is_nothing_to_show(): void
    {
        $this->register($this->upcomingEvent());

        $this->assertNull($this->ask($this->owner));
    }

    public function test_the_count_has_no_link_and_is_a_plain_number(): void
    {
        $this->register($this->pastEvent());

        $stat = $this->ask($this->owner);

        $this->assertInstanceOf(ProfileStatDTO::class, $stat);
        $this->assertNull($stat->url);
    }

    public function test_it_is_counted_in_the_whole_profile_only_for_who_may_see_it(): void
    {
        $this->register($this->pastEvent());
        $renderer = $this->app->make(ProfileRendererInterface::class);

        $forOwner = $renderer->render($this->ownerDto(), $this->owner);
        $forGuest = $renderer->render($this->ownerDto(), null);

        $this->assertSame(['event-registrations.attended'], array_map(static fn ($stat): string => $stat->key, $forOwner->stats));
        $this->assertSame([], $forGuest->stats);
    }

    public function test_the_count_costs_one_query_whatever_the_number_of_events(): void
    {
        foreach (range(1, 6) as $days) {
            $this->register($this->pastEvent("Pasado {$days}", $days));
        }
        $stat = $this->stat();

        DB::enableQueryLog();
        $stat->stat($this->ownerDto(), $this->owner);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(1, $queries);
    }

    public function test_the_rule_is_asked_once_per_person_whatever_the_number_of_events(): void
    {
        $staff = $this->viewers()['staff who open the tab'];
        $this->register($this->pastEvent('Pasado 1', 1));
        $this->queriesFor($staff);

        $one = $this->queriesFor($staff);

        foreach (range(2, 6) as $days) {
            $this->register($this->pastEvent("Pasado {$days}", $days));
        }

        $this->assertSame($one, $this->queriesFor($staff), 'More events must not mean more checks');
    }

    private function queriesFor(?Authenticatable $viewer): int
    {
        Cache::flush();
        $this->app->forgetScopedInstances();
        $stat = $this->stat();

        DB::enableQueryLog();
        DB::flushQueryLog();
        $stat->stat($this->ownerDto(), $viewer);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    }

    private function stat(): AttendedEventsProfileStat
    {
        return $this->app->make(AttendedEventsProfileStat::class);
    }

    private function ask(?Authenticatable $viewer): ?ProfileStatDTO
    {
        return $this->stat()->stat($this->ownerDto(), $viewer);
    }
}
