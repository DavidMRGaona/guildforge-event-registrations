<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Integration\Activity;

use App\Application\Activity\Services\ActivityPublisherInterface;
use App\Domain\Activity\Events\ActivityRecorded;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Domain\Repositories\EventRegistrationRepositoryInterface;
use Modules\EventRegistrations\Domain\ValueObjects\EventRegistrationId;
use Modules\EventRegistrations\Infrastructure\Activity\EndedEventActivityAnnouncer;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationModel;
use Modules\EventRegistrations\Tests\Support\BuildsProfileRegistrations;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Everything that changes the facts of an event that already ended is published again, so whoever
 * keeps a record corrects it: a registration confirmed, cancelled or removed after the end, the
 * event unpublished, deleted or with its end moved, an account removed.
 */
final class ActivityChangePublishingTest extends ModuleTestCase
{
    use BuildsProfileRegistrations;

    private const string TYPE = 'event-registrations.event';

    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pinTheClock();
    }

    public function test_a_registration_confirmed_after_the_end_is_published(): void
    {
        $event = $this->pastEvent();
        $registration = $this->register($event, RegistrationState::Pending);
        $this->listen();

        $registration->update(['state' => RegistrationState::Confirmed]);

        $this->assertPublished($event->id);
        Event::assertDispatchedTimes(ActivityRecorded::class, 1);
    }

    public function test_a_registration_created_already_confirmed_after_the_end_is_published(): void
    {
        $event = $this->pastEvent();
        $this->listen();

        $this->register($event);

        $this->assertPublished($event->id);
    }

    public function test_a_confirmed_registration_cancelled_after_the_end_is_published(): void
    {
        $event = $this->pastEvent();
        $registration = $this->register($event);
        $this->listen();

        $registration->update(['state' => RegistrationState::Cancelled]);

        $this->assertPublished($event->id);
    }

    public function test_a_confirmed_registration_deleted_after_the_end_is_published(): void
    {
        $event = $this->pastEvent();
        $registration = $this->register($event);
        $this->listen();

        $registration->delete();

        $this->assertPublished($event->id);
    }

    public function test_deleting_through_the_repository_is_published_too(): void
    {
        $event = $this->pastEvent();
        $registration = $this->register($event);
        $this->listen();

        $this->app->make(EventRegistrationRepositoryInterface::class)->delete(EventRegistrationId::fromString($registration->id));

        $this->assertPublished($event->id);
        $this->assertSame(0, EventRegistrationModel::query()->count());
    }

    public function test_a_registration_moved_to_another_event_publishes_both(): void
    {
        $first = $this->pastEvent('Primero');
        $second = $this->pastEvent('Segundo', 20);
        $registration = $this->register($first);
        $this->listen();

        $registration->update(['event_id' => $second->id]);

        $this->assertPublished($first->id);
        $this->assertPublished($second->id);
    }

    public function test_changes_that_do_not_touch_the_facts_publish_nothing(): void
    {
        $event = $this->pastEvent();
        $confirmed = $this->register($event);
        $waiting = $this->register($event, RegistrationState::WaitingList, UserModel::factory()->create());
        $this->listen();

        $confirmed->update(['notes' => 'Llegará tarde', 'admin_notes' => 'Paga en puerta']);
        $waiting->update(['state' => RegistrationState::Pending]);
        $waiting->update(['state' => RegistrationState::Cancelled]);
        $waiting->delete();

        Event::assertNotDispatched(ActivityRecorded::class);
    }

    public function test_registrations_of_an_event_that_has_not_ended_wait_for_its_end(): void
    {
        $coming = $this->upcomingEvent();
        $this->listen();

        $registration = $this->register($coming);
        $registration->update(['state' => RegistrationState::Cancelled]);
        $registration->delete();

        Event::assertNotDispatched(ActivityRecorded::class);
    }

    public function test_registrations_of_an_unpublished_event_publish_nothing(): void
    {
        $draft = $this->pastEvent('Borrador');
        $draft->update(['is_published' => false]);
        $this->listen();

        $this->register($draft);

        Event::assertNotDispatched(ActivityRecorded::class);
    }

    public function test_unpublishing_an_ended_event_is_published(): void
    {
        $event = $this->pastEvent();
        $this->register($event);
        $this->listen();

        $event->update(['is_published' => false]);

        $this->assertPublished($event->id);
    }

    public function test_publishing_an_ended_event_again_is_published(): void
    {
        $event = $this->pastEvent();
        $this->register($event);
        $event->update(['is_published' => false]);
        $this->listen();

        $event->update(['is_published' => true]);

        $this->assertPublished($event->id);
    }

    public function test_a_save_that_changes_neither_publication_nor_end_publishes_nothing(): void
    {
        $event = $this->pastEvent();
        $this->register($event);
        $this->listen();

        $event->update(['title' => 'Otro título', 'location' => 'Otra sala']);

        Event::assertNotDispatched(ActivityRecorded::class);
    }

    public function test_an_event_that_has_not_ended_publishes_nothing_when_edited(): void
    {
        $coming = $this->upcomingEvent();
        $this->register($coming);
        $this->listen();

        $coming->update(['is_published' => false]);
        $coming->update(['is_published' => true]);
        $coming->update(['end_date' => new DateTimeImmutable('2031-01-01 10:00:00 UTC')]);

        Event::assertNotDispatched(ActivityRecorded::class);
    }

    public function test_moving_the_end_of_an_ended_event_into_the_future_is_published(): void
    {
        $event = $this->pastEvent();
        $this->register($event);
        $this->listen();

        $event->update(['end_date' => new DateTimeImmutable('2030-07-01 10:00:00 UTC')]);

        $this->assertPublished($event->id);
    }

    public function test_moving_the_end_of_a_coming_event_into_the_past_is_published(): void
    {
        $coming = $this->upcomingEvent();
        $this->register($coming);
        $this->listen();

        $coming->update(['start_date' => new DateTimeImmutable('2030-06-14 08:00:00 UTC'), 'end_date' => new DateTimeImmutable('2030-06-14 12:00:00 UTC')]);

        $this->assertPublished($coming->id);
    }

    public function test_moving_the_end_of_an_ended_event_to_another_past_date_is_published(): void
    {
        $event = $this->pastEvent();
        $this->register($event);
        $this->listen();

        $event->update(['end_date' => new DateTimeImmutable('2030-06-06 12:00:00 UTC')]);

        $this->assertPublished($event->id);
    }

    public function test_deleting_an_ended_published_event_is_published(): void
    {
        $event = $this->pastEvent();
        $this->register($event);
        $this->listen();

        $event->delete();

        $this->assertPublished($event->id);
        $this->assertSame(0, DB::table(EndedEventActivityAnnouncer::TABLE)->where('event_id', $event->id)->count());
    }

    public function test_deleting_an_event_that_never_counted_publishes_nothing(): void
    {
        $coming = $this->upcomingEvent();
        $draft = $this->pastEvent('Borrador');
        $draft->update(['is_published' => false]);
        $this->listen();

        $coming->delete();
        $draft->delete();

        Event::assertNotDispatched(ActivityRecorded::class);
    }

    public function test_removing_an_account_for_good_publishes_the_events_it_attended_once_it_is_gone(): void
    {
        $attended = $this->pastEvent('Asistido');
        $other = $this->pastEvent('Otro', 20);
        $comingEvent = $this->upcomingEvent();
        $leaver = UserModel::factory()->create();
        $this->register($attended, user: $leaver);
        $this->register($other, RegistrationState::Cancelled, $leaver);
        $this->register($comingEvent, user: $leaver);
        $this->register($other);
        // A forced deletion opens no transaction, so what the consumer reads is what is stored when
        // the publisher is called: the leaver's registrations must be gone by then
        /** @var \ArrayObject<string, int> $remainingOfLeaver */
        $remainingOfLeaver = new \ArrayObject;
        $leaverId = $leaver->id;
        $this->app->instance(ActivityPublisherInterface::class, new readonly class($remainingOfLeaver, $leaverId) implements ActivityPublisherInterface
        {
            /** @param  \ArrayObject<string, int>  $remaining */
            public function __construct(private \ArrayObject $remaining, private string $leaverId) {}

            public function recorded(string $sourceType, string $subjectId): void
            {
                $this->remaining[$subjectId] = EventRegistrationModel::query()->where('event_id', $subjectId)->where('user_id', $this->leaverId)->count();
            }
        });

        $leaver->forceDelete();

        $this->assertSame([$attended->id => 0], $remainingOfLeaver->getArrayCopy());
    }

    public function test_deactivating_an_account_publishes_nothing_because_its_facts_stay(): void
    {
        $event = $this->pastEvent();
        $user = UserModel::factory()->create();
        $this->register($event, user: $user);
        $this->listen();

        $user->delete();

        Event::assertNotDispatched(ActivityRecorded::class);
    }

    private function assertPublished(string $eventId): void
    {
        Event::assertDispatched(ActivityRecorded::class, static fn (ActivityRecorded $recorded): bool => $recorded->sourceType === self::TYPE && $recorded->subjectId === $eventId);
    }

    /**
     * Listens from here on, so what the setup did is not counted.
     */
    private function listen(): void
    {
        Event::fake([ActivityRecorded::class]);
        // The publisher is scoped and keeps the dispatcher it was built with
        $this->app->forgetInstance(ActivityPublisherInterface::class);
    }
}
