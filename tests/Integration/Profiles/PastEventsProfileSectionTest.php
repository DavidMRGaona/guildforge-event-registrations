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
use Modules\EventRegistrations\Infrastructure\Profiles\PastEventsProfileSection;
use Modules\EventRegistrations\Infrastructure\Profiles\UpcomingEventsProfileSection;
use Modules\EventRegistrations\Tests\Support\BuildsProfileRegistrations;
use Modules\EventRegistrations\Tests\Support\FixedClock;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Modules\ModuleTestCase;

/**
 * «Últimos eventos asistidos» on a public profile: the latest published events that ended with the
 * owner confirmed in them, shown only to the people the module lets see that person's registrations.
 */
final class PastEventsProfileSectionTest extends ModuleTestCase
{
    use BuildsProfileRegistrations;

    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pinTheClock();
    }

    public function test_the_module_offers_the_section_in_the_aside_right_after_the_upcoming_events(): void
    {
        $registration = $this->app->make(ProfileSectionRegistryInterface::class)->find('event-registrations.past');

        $this->assertNotNull($registration);
        $this->assertSame('event-registrations', $registration->module);
        $this->assertSame(PastEventsProfileSection::class, $registration->class);

        $section = $this->section();
        $upcoming = $this->app->make(UpcomingEventsProfileSection::class);
        $this->assertSame(ProfileRegion::Aside, $section->region());
        $this->assertSame('components/PastEventsSection.vue', $section->component());
        $this->assertSame($upcoming->order() + 1, $section->order());
        $this->assertTrue($section->enabledByDefault());
        $this->assertTrue($section->isAvailable());
        $this->assertNotSame('', $section->label());
        $this->assertNotSame('', $section->description());
        $this->assertNotSame($upcoming->label(), $section->label());
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function whoSees(): array
    {
        return UpcomingEventsProfileSectionTest::whoSees();
    }

    #[DataProvider('whoSees')]
    public function test_only_who_may_see_the_registrations_sees_the_event(string $who, bool $sees): void
    {
        $this->register($this->pastEvent('Torneo de cartas'));
        $viewer = $this->viewers()[$who];

        if ($sees) {
            $this->assertSame(['Torneo de cartas'], $this->titles($viewer));

            return;
        }

        $this->expectException(NothingToDrawException::class);
        $this->props($viewer);
    }

    public function test_what_is_hidden_from_a_viewer_is_not_drawn_in_the_whole_profile(): void
    {
        $this->register($this->upcomingEvent());
        $this->register($this->pastEvent());
        $renderer = $this->app->make(ProfileRendererInterface::class);

        $forOwner = $renderer->render($this->ownerDto(), $this->owner);
        $forMember = $renderer->render($this->ownerDto(), UserModel::factory()->create());
        $forGuest = $renderer->render($this->ownerDto(), null);

        $keys = static fn (array $sections): array => array_map(static fn ($section): string => $section->key, $sections);
        $this->assertSame(['event-registrations.upcoming', 'event-registrations.past'], $keys($forOwner->aside));
        $this->assertSame([], $keys($forMember->aside));
        $this->assertSame([], $keys($forGuest->aside));
    }

    public function test_only_confirmed_registrations_count(): void
    {
        foreach ([RegistrationState::Pending, RegistrationState::WaitingList, RegistrationState::Rejected, RegistrationState::Cancelled] as $state) {
            $this->register($this->pastEvent("Evento {$state->value}"), $state);
        }
        $this->register($this->pastEvent('Confirmado'));

        $this->assertSame(['Confirmado'], $this->titles($this->owner));
    }

    public function test_events_that_are_not_published_are_left_out(): void
    {
        $this->register($this->pastEvent('Publicado'));
        $draft = $this->pastEvent('Borrador');
        $draft->forceFill(['is_published' => false])->save();
        $this->register($draft);

        $this->assertSame(['Publicado'], $this->titles($this->owner));
    }

    public function test_only_events_that_already_ended_are_listed(): void
    {
        $this->register($this->upcomingEvent('Todavía no'));
        $this->register($this->eventAt('2030-06-15 09:00:00', '2030-06-15 18:00:00', ['title' => 'En curso']));
        $this->register($this->eventAt('2030-06-15 10:00:00', '2030-06-15 12:00:00', ['title' => 'Termina ahora']));
        $this->register($this->eventAt('2030-06-15 08:00:00', '2030-06-15 11:59:59', ['title' => 'Terminó hace un segundo']));

        $this->assertSame(['Terminó hace un segundo'], $this->titles($this->owner));
    }

    public function test_the_events_come_latest_first_and_a_handful_at_most(): void
    {
        foreach ([9, 3, 7, 1, 5, 8, 2] as $days) {
            $this->register($this->pastEvent("Hace {$days}", $days));
        }

        $this->assertSame(
            ['Hace 1', 'Hace 2', 'Hace 3', 'Hace 5', 'Hace 7'],
            $this->titles($this->owner),
        );
    }

    public function test_the_latest_to_end_comes_first_even_when_it_began_earlier(): void
    {
        $this->register($this->eventAt('2030-06-01 09:00:00', '2030-06-14 20:00:00', ['title' => 'Torneo de dos semanas']));
        $this->register($this->eventAt('2030-06-10 18:00:00', '2030-06-10 22:00:00', ['title' => 'Quedada a mitad del torneo']));
        $this->register($this->eventAt('2030-06-12 10:00:00', '2030-06-12 14:00:00', ['title' => 'Jornada de rol']));

        $this->assertSame(
            ['Torneo de dos semanas', 'Jornada de rol', 'Quedada a mitad del torneo'],
            $this->titles($this->owner),
        );
    }

    public function test_events_that_end_together_come_latest_created_first(): void
    {
        $end = '2030-06-14 20:00:00';
        $this->register($this->eventAt('2030-06-14 16:00:00', $end, ['title' => 'Creado primero', 'created_at' => new \DateTimeImmutable('2030-05-01 10:00:00 UTC')]));
        $this->register($this->eventAt('2030-06-14 10:00:00', $end, ['title' => 'Creado después', 'created_at' => new \DateTimeImmutable('2030-05-03 10:00:00 UTC')]));
        $this->register($this->eventAt('2030-06-14 18:00:00', $end, ['title' => 'Creado en medio', 'created_at' => new \DateTimeImmutable('2030-05-02 10:00:00 UTC')]));

        $this->assertSame(
            ['Creado después', 'Creado en medio', 'Creado primero'],
            $this->titles($this->owner),
        );
    }

    public function test_other_peoples_registrations_are_not_listed(): void
    {
        $other = UserModel::factory()->create();
        $this->register($this->pastEvent('De otra persona'), RegistrationState::Confirmed, $other);

        $this->expectException(NothingToDrawException::class);
        $this->props($this->owner);
    }

    public function test_nothing_to_show_throws(): void
    {
        $this->expectException(NothingToDrawException::class);

        $this->props($this->owner);
    }

    public function test_upcoming_events_alone_leave_it_empty(): void
    {
        $this->register($this->upcomingEvent());

        $this->expectException(NothingToDrawException::class);
        $this->props($this->owner);
    }

    public function test_an_event_carries_only_what_the_event_page_already_shows(): void
    {
        $event = $this->pastEvent('Torneo de cartas');
        $event->forceFill(['location' => 'Local de la asociación'])->save();
        $this->register($event);

        $props = $this->props($this->owner);

        $this->assertSame(['events'], array_keys($props));
        $this->assertSame([[
            'title' => 'Torneo de cartas',
            'url' => "/eventos/{$event->slug}",
            'startsAt' => '2030-06-05T14:00:00+02:00',
            'location' => 'Local de la asociación',
        ]], $props['events']);

        $json = json_encode($props, JSON_THROW_ON_ERROR);
        foreach (['Vegetarian', 'Internal', '600 000 000', 'confirmed', $this->owner->email] as $private) {
            $this->assertStringNotContainsString($private, $json);
        }
    }

    public function test_the_dates_follow_the_display_zone_not_the_storage_zone(): void
    {
        $this->app->instance(Clock::class, FixedClock::at('2030-02-01 00:00:00 UTC'));
        $this->register($this->eventAt('2030-01-20 22:30:00', '2030-01-20 23:30:00', ['title' => 'Invierno']));

        $this->assertSame('2030-01-20T23:30:00+01:00', $this->props($this->owner)['events'][0]['startsAt']);
    }

    public function test_the_list_costs_one_query_whatever_the_number_of_events(): void
    {
        foreach (range(1, 5) as $days) {
            $this->register($this->pastEvent("Evento {$days}", $days));
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
        $this->register($this->pastEvent('Evento 1', 1));
        $this->queriesFor($staff);

        $one = $this->queriesFor($staff);

        foreach (range(2, 5) as $days) {
            $this->register($this->pastEvent("Evento {$days}", $days));
        }
        $five = $this->queriesFor($staff);

        $this->assertSame($one, $five, 'More events must not mean more checks');
    }

    public function test_the_section_keeps_nothing_of_who_looked_before(): void
    {
        $this->register($this->pastEvent('Torneo de cartas'));
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

    private function section(): PastEventsProfileSection
    {
        return $this->app->make(PastEventsProfileSection::class);
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
