<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Integration\Activity;

use App\Application\Activity\Contracts\ActivitySourceInterface;
use App\Application\Activity\Contracts\OffersActivityAttributes;
use App\Application\Activity\DTOs\ActivityFactDTO;
use App\Domain\Activity\ActivityKind;
use App\Domain\Activity\ActivityRole;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use InvalidArgumentException;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Infrastructure\Activity\EventActivitySource;
use Modules\EventRegistrations\Infrastructure\Profiles\ProfileEvents;
use Modules\EventRegistrations\Tests\Support\BuildsProfileRegistrations;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Activity\ActivitySourceTestCase;

/**
 * «Eventos asistidos» as an activity source (core#774): every published event that has ended, with
 * each confirmed registration of an account as an `attendee` fact. It counts exactly what the
 * profile's `event-registrations.attended` stat counts, but answers the same for everybody.
 */
final class EventActivitySourceTest extends ActivitySourceTestCase
{
    use BuildsProfileRegistrations;

    protected ?string $moduleName = 'event-registrations';

    protected function setUp(): void
    {
        parent::setUp();

        $this->pinTheClock();
    }

    protected function activitySource(): ActivitySourceInterface
    {
        return $this->app->make(EventActivitySource::class);
    }

    protected function createCountedActivitySubject(): string
    {
        $event = $this->pastEvent('Jornada '.uniqid());
        $this->register($event, user: UserModel::factory()->create());
        $this->register($event);

        return $event->id;
    }

    public function test_the_source_is_about_events_and_offers_no_attributes(): void
    {
        $source = $this->activitySource();

        $this->assertSame('event-registrations.event', $source->type());
        $this->assertSame(ActivityKind::Event, $source->kind());
        $this->assertNotSame('', $source->label());
        $this->assertNotInstanceOf(OffersActivityAttributes::class, $source);
    }

    public function test_every_confirmed_registration_of_an_ended_published_event_is_an_attendee_fact_at_its_end(): void
    {
        $event = $this->pastEvent();
        $other = UserModel::factory()->create();
        $this->register($event);
        $this->register($event, user: $other);

        $facts = $this->activitySource()->facts($event->id);

        $this->assertCount(2, $facts);
        $byUser = [];
        foreach ($facts as $fact) {
            $this->assertSame(ActivityRole::Attendee, $fact->role);
            $this->assertSame([], $fact->attributes);
            $this->assertNull($fact->placement);
            $this->assertEquals(DateTimeImmutable::createFromInterface($event->end_date), $fact->occurredAt);
            $byUser[$fact->userId] = true;
        }
        $this->assertEqualsCanonicalizing([$this->owner->id, $other->id], array_keys($byUser));
    }

    public function test_an_id_that_is_not_a_uuid_has_no_facts(): void
    {
        $this->assertSame([], $this->activitySource()->facts('not-an-id'));
        $this->assertSame([], $this->activitySource()->facts(''));
    }

    /**
     * @return array<string, array{RegistrationState}>
     */
    public static function statesThatNeverCount(): array
    {
        return [
            'pending' => [RegistrationState::Pending],
            'waiting list' => [RegistrationState::WaitingList],
            'rejected' => [RegistrationState::Rejected],
            'cancelled' => [RegistrationState::Cancelled],
        ];
    }

    #[DataProvider('statesThatNeverCount')]
    public function test_only_confirmed_registrations_count(RegistrationState $state): void
    {
        $event = $this->pastEvent();
        $this->register($event, $state);

        $this->assertSame([], $this->activitySource()->facts($event->id));
        $this->assertSame([], $this->history());
    }

    public function test_an_event_still_running_or_to_come_does_not_count_yet(): void
    {
        $running = $this->eventAt('2030-06-15 09:00:00', '2030-06-15 12:00:01');
        $coming = $this->upcomingEvent();
        $this->register($running);
        $this->register($coming);

        $this->assertSame([], $this->activitySource()->facts($running->id));
        $this->assertSame([], $this->activitySource()->facts($coming->id));
        $this->assertSame([], $this->history());
    }

    public function test_an_unpublished_event_never_counts(): void
    {
        $draft = $this->pastEvent('Borrador');
        $this->register($draft);
        $draft->update(['is_published' => false]);

        $this->assertSame([], $this->activitySource()->facts($draft->id));
        $this->assertSame([], $this->history());
    }

    public function test_a_deactivated_account_keeps_its_fact_and_a_removed_registration_loses_it(): void
    {
        $event = $this->pastEvent();
        $leaver = UserModel::factory()->create();
        $deactivated = UserModel::factory()->create();
        $this->register($event, user: $deactivated);
        $registration = $this->register($event, user: $leaver);
        $deactivated->delete();
        $registration->delete();

        $facts = $this->activitySource()->facts($event->id);

        $this->assertSame([$deactivated->id], array_map(static fn (ActivityFactDTO $fact): string => $fact->userId, $facts));
    }

    public function test_the_answer_is_the_same_whoever_is_signed_in(): void
    {
        $event = $this->pastEvent();
        $this->register($event);
        $source = $this->activitySource();

        $asGuest = [$this->comparable($source->facts($event->id)), $this->history()];
        $this->actingAs(UserModel::factory()->admin()->create());
        $asAdmin = [$this->comparable($source->facts($event->id)), $this->history()];
        $this->actingAs(UserModel::factory()->create());
        $asMember = [$this->comparable($source->facts($event->id)), $this->history()];

        $this->assertNotSame([], $asGuest[0]);
        $this->assertSame($asGuest, $asAdmin);
        $this->assertSame($asGuest, $asMember);
    }

    public function test_the_history_lists_the_counted_events_only_in_a_stable_order_and_in_pages(): void
    {
        $ids = [];
        for ($i = 0; $i < 5; $i++) {
            $ids[] = $this->createCountedActivitySubject();
        }
        // None of these counts
        $this->register($this->upcomingEvent());
        $this->register($this->pastEvent('Sin confirmar'), RegistrationState::Pending);
        $this->pastEvent('Vacío');
        sort($ids);

        $source = $this->activitySource();
        $walked = [];
        $cursor = null;
        $pages = 0;

        do {
            $page = $source->history($cursor, 2);
            foreach ($page->subjects as $subject) {
                $walked[] = $subject->subjectId;
            }
            $cursor = $page->nextCursor;
            $pages++;
        } while ($cursor !== null);

        $this->assertSame($ids, $walked);
        $this->assertSame(3, $pages);
        $this->assertSame($ids, $this->history());
    }

    public function test_the_last_page_has_no_cursor_even_when_it_is_full(): void
    {
        $this->createCountedActivitySubject();
        $this->createCountedActivitySubject();

        $page = $this->activitySource()->history(null, 2);

        $this->assertCount(2, $page->subjects);
        $this->assertNull($page->nextCursor);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unreadableCursors(): array
    {
        return [
            'plain text' => ['hello'],
            'a bare id' => ['0198d8d2-2c8a-7e43-9b0c-8c0f4d2b7a11'],
            'wrong prefix' => [rtrim(strtr(base64_encode('gt1|session|0198d8d2-2c8a-7e43-9b0c-8c0f4d2b7a11'), '+/', '-_'), '=')],
            'not an id' => [rtrim(strtr(base64_encode('er1|not-an-id'), '+/', '-_'), '=')],
        ];
    }

    #[DataProvider('unreadableCursors')]
    public function test_a_cursor_the_source_cannot_read_throws(string $cursor): void
    {
        $this->createCountedActivitySubject();

        $this->expectException(InvalidArgumentException::class);

        $this->activitySource()->history($cursor, 5);
    }

    public function test_it_counts_exactly_what_the_profile_stat_counts(): void
    {
        $counted = [$this->pastEvent('Uno'), $this->pastEvent('Dos', 20)];
        foreach ($counted as $event) {
            $this->register($event);
        }
        $this->register($this->pastEvent('Pendiente'), RegistrationState::Pending);
        $this->register($this->upcomingEvent());
        $draft = $this->pastEvent('Borrador');
        $this->register($draft);
        $draft->update(['is_published' => false]);

        $factsOfOwner = 0;
        foreach ($this->activitySource()->history(null, 50)->subjects as $subject) {
            foreach ($subject->facts as $fact) {
                if ($fact->userId === $this->owner->id) {
                    $factsOfOwner++;
                }
            }
        }

        $this->assertSame(2, $factsOfOwner);
        $this->assertSame($factsOfOwner, $this->app->make(ProfileEvents::class)->attendedCount($this->owner->id));
    }

    public function test_describe_gives_title_and_path_of_published_events_only(): void
    {
        $shown = $this->pastEvent('Torneo de otoño');
        $draft = $this->pastEvent('Borrador oculto');
        $draft->update(['is_published' => false]);
        $source = $this->activitySource();

        $forGuest = $source->describe([$shown->id, $draft->id, 'not-an-id'], null);
        $forMember = $source->describe([$shown->id, $draft->id], UserModel::factory()->create());

        $this->assertSame([$shown->id], array_keys($forGuest));
        $this->assertSame('Torneo de otoño', $forGuest[$shown->id]->title);
        $this->assertSame('/eventos/'.$shown->slug, $forGuest[$shown->id]->url);
        $this->assertEquals($forGuest, $forMember);
    }

    public function test_describe_does_not_require_the_event_to_have_ended(): void
    {
        $coming = $this->upcomingEvent('Próxima jornada');

        $described = $this->activitySource()->describe([$coming->id], null);

        $this->assertSame('Próxima jornada', $described[$coming->id]->title);
    }

    /**
     * @return list<string>
     */
    private function history(): array
    {
        return array_map(
            static fn ($subject): string => $subject->subjectId,
            $this->activitySource()->history(null, 50)->subjects,
        );
    }

    /**
     * @param  list<ActivityFactDTO>  $facts
     * @return list<string>
     */
    private function comparable(array $facts): array
    {
        $rows = array_map(
            static fn (ActivityFactDTO $fact): string => $fact->userId.'|'.$fact->role->value.'|'.$fact->occurredAt->format('c'),
            $facts,
        );
        sort($rows);

        return $rows;
    }
}
