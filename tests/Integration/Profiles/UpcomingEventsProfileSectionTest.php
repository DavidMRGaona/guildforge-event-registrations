<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Integration\Profiles;

use App\Application\Exceptions\NothingToDrawException;
use App\Application\Profiles\Services\ProfileRendererInterface;
use App\Application\Profiles\Services\ProfileSectionRegistryInterface;
use App\Domain\Profiles\ProfileRegion;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Domain\Services\Clock;
use Modules\EventRegistrations\Infrastructure\Profiles\UpcomingEventsProfileSection;
use Modules\EventRegistrations\Tests\Support\BuildsProfileRegistrations;
use Modules\EventRegistrations\Tests\Support\FixedClock;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Modules\ModuleTestCase;

/**
 * «Próximos eventos» on a public profile: the events the owner is confirmed in, shown only to the
 * people the module lets see that person's registrations.
 */
final class UpcomingEventsProfileSectionTest extends ModuleTestCase
{
    use BuildsProfileRegistrations;

    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pinTheClock();
    }

    public function test_the_module_offers_the_section_in_the_aside(): void
    {
        $registration = $this->app->make(ProfileSectionRegistryInterface::class)->find('event-registrations.upcoming');

        $this->assertNotNull($registration);
        $this->assertSame('event-registrations', $registration->module);
        $this->assertSame(UpcomingEventsProfileSection::class, $registration->class);

        $section = $this->section();
        $this->assertSame(ProfileRegion::Aside, $section->region());
        $this->assertSame('components/UpcomingEventsSection.vue', $section->component());
        $this->assertTrue($section->enabledByDefault());
        $this->assertTrue($section->isAvailable());
        $this->assertNotSame('', $section->label());
        $this->assertNotSame('', $section->description());
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function whoSees(): array
    {
        return [
            'the owner' => ['owner', true],
            'an administrator' => ['admin', true],
            'staff who open the registrations tab of the event' => ['staff who open the tab', true],
            'the same staff with registrations.view on top' => ['staff who open the tab, plus view', true],
            'staff with only registrations.view' => ['staff with only registrations.view', false],
            'staff who can only list registrations' => ['staff who can only list registrations', false],
            'staff without the events resource' => ['staff without the events resource', false],
            'staff who cannot edit the event' => ['staff who cannot edit the event', false],
            'the tab permissions without the panel' => ['the tab permissions without the panel', false],
            'a guest' => ['guest', false],
            'another member' => ['another member', false],
        ];
    }

    #[DataProvider('whoSees')]
    public function test_only_who_may_see_the_registrations_sees_the_event(string $who, bool $sees): void
    {
        $this->register($this->upcomingEvent('Jornada de rol'));
        $viewer = $this->viewers()[$who];

        if ($sees) {
            $this->assertSame(['Jornada de rol'], $this->titles($viewer));

            return;
        }

        $this->expectException(NothingToDrawException::class);
        $this->props($viewer);
    }

    public function test_what_is_hidden_from_a_viewer_is_not_drawn_in_the_whole_profile(): void
    {
        $this->register($this->upcomingEvent());
        $renderer = $this->app->make(ProfileRendererInterface::class);

        $forOwner = $renderer->render($this->ownerDto(), $this->owner);
        $forGuest = $renderer->render($this->ownerDto(), null);

        $this->assertSame(['event-registrations.upcoming'], array_map(static fn ($section): string => $section->key, $forOwner->aside));
        $this->assertSame([], array_map(static fn ($section): string => $section->key, $forGuest->aside));
    }

    public function test_only_confirmed_registrations_count(): void
    {
        foreach ([RegistrationState::Pending, RegistrationState::WaitingList, RegistrationState::Rejected, RegistrationState::Cancelled] as $state) {
            $this->register($this->upcomingEvent("Evento {$state->value}"), $state);
        }
        $this->register($this->upcomingEvent('Confirmado'));

        $this->assertSame(['Confirmado'], $this->titles($this->owner));
    }

    public function test_events_that_are_not_published_are_left_out(): void
    {
        $this->register($this->upcomingEvent('Publicado'));
        $draft = $this->upcomingEvent('Borrador');
        $draft->forceFill(['is_published' => false])->save();
        $this->register($draft);

        $this->assertSame(['Publicado'], $this->titles($this->owner));
    }

    public function test_events_that_already_ended_are_left_out_and_one_in_progress_is_kept(): void
    {
        $this->register($this->pastEvent('Ya terminado'));
        $this->register($this->eventAt('2030-06-15 08:00:00', '2030-06-15 11:59:59', ['title' => 'Terminó hace un segundo']));
        $this->register($this->eventAt('2030-06-15 09:00:00', '2030-06-15 18:00:00', ['title' => 'En curso']));
        $this->register($this->eventAt('2030-06-15 10:00:00', '2030-06-15 12:00:00', ['title' => 'Termina ahora']));

        $this->assertSame(['En curso', 'Termina ahora'], $this->titles($this->owner));
    }

    public function test_the_events_come_soonest_first_and_a_handful_at_most(): void
    {
        foreach ([9, 3, 7, 1, 5, 8, 2] as $days) {
            $this->register($this->upcomingEvent("Dentro de {$days}", $days));
        }

        $this->assertSame(
            ['Dentro de 1', 'Dentro de 2', 'Dentro de 3', 'Dentro de 5', 'Dentro de 7'],
            $this->titles($this->owner),
        );
    }

    public function test_other_peoples_registrations_are_not_listed(): void
    {
        $other = UserModel::factory()->create();
        $this->register($this->upcomingEvent('De otra persona'), RegistrationState::Confirmed, $other);

        $this->expectException(NothingToDrawException::class);
        $this->props($this->owner);
    }

    public function test_nothing_to_show_throws(): void
    {
        $this->expectException(NothingToDrawException::class);

        $this->props($this->owner);
    }

    public function test_an_event_carries_only_what_the_event_page_already_shows(): void
    {
        $event = $this->upcomingEvent('Jornada de rol');
        $event->forceFill(['location' => 'Local de la asociación'])->save();
        $this->register($event);

        $props = $this->props($this->owner);

        $this->assertSame(['events'], array_keys($props));
        $this->assertSame([[
            'title' => 'Jornada de rol',
            'url' => "/eventos/{$event->slug}",
            'startsAt' => '2030-06-25T14:00:00+02:00',
            'location' => 'Local de la asociación',
        ]], $props['events']);

        $json = json_encode($props, JSON_THROW_ON_ERROR);
        foreach (['Vegetarian', 'Internal', '600 000 000', 'confirmed', $this->owner->email] as $private) {
            $this->assertStringNotContainsString($private, $json);
        }
    }

    public function test_the_dates_follow_the_display_zone_not_the_storage_zone(): void
    {
        $this->app->instance(Clock::class, FixedClock::at('2030-01-01 00:00:00 UTC'));
        $this->register($this->eventAt('2030-01-20 22:30:00', '2030-01-20 23:30:00', ['title' => 'Invierno']));

        $this->assertSame('2030-01-20T23:30:00+01:00', $this->props($this->owner)['events'][0]['startsAt']);
    }

    public function test_the_list_costs_one_query_whatever_the_number_of_events(): void
    {
        foreach (range(1, 5) as $days) {
            $this->register($this->upcomingEvent("Evento {$days}", $days));
        }
        $section = $this->section();

        DB::enableQueryLog();
        $section->props($this->ownerDto(), $this->owner);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(1, $queries);
    }

    public function test_the_rule_is_asked_once_per_person_whatever_the_number_of_events(): void
    {
        $staff = $this->viewers()['staff who open the tab'];
        $this->register($this->upcomingEvent('Evento 1', 1));
        $this->queriesFor($staff);

        $one = $this->queriesFor($staff);

        foreach (range(2, 5) as $days) {
            $this->register($this->upcomingEvent("Evento {$days}", $days));
        }
        $five = $this->queriesFor($staff);

        $this->assertSame($one, $five, 'More events must not mean more checks');
    }

    public function test_the_section_keeps_nothing_of_who_looked_before(): void
    {
        $this->register($this->upcomingEvent('Jornada de rol'));
        $section = $this->section();

        $seenByTheOwner = $section->props($this->ownerDto(), $this->owner);

        try {
            $section->props($this->ownerDto(), null);
            $this->fail('A guest must not see the registrations');
        } catch (NothingToDrawException) {
            $this->assertSame($seenByTheOwner, $section->props($this->ownerDto(), $this->owner));
        }
    }

    /**
     * The queries the section runs for this person, with nothing remembered from a former call.
     */
    private function queriesFor(?Authenticatable $viewer): int
    {
        Cache::flush();
        $this->app->forgetScopedInstances();
        $section = $this->section();

        DB::enableQueryLog();
        DB::flushQueryLog();
        $section->props($this->ownerDto(), $viewer);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    }

    private function section(): UpcomingEventsProfileSection
    {
        return $this->app->make(UpcomingEventsProfileSection::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function props(?Authenticatable $viewer): array
    {
        return $this->section()->props($this->ownerDto(), $viewer);
    }

    /**
     * @return list<string>
     */
    private function titles(?Authenticatable $viewer): array
    {
        /** @var list<array{title: string}> $events */
        $events = $this->props($viewer)['events'];

        return array_map(static fn (array $event): string => $event['title'], $events);
    }
}
