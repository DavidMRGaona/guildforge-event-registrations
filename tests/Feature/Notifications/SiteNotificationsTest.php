<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Feature\Notifications;

use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\SiteNotifications\Contracts\HasSiteNotification;
use App\Application\SiteNotifications\Services\SiteNotificationServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\SiteNews\Jobs\AnnounceSiteNewsJob;
use Illuminate\Support\Facades\Queue;
use Modules\EventRegistrations\Domain\Entities\EventRegistration;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Domain\ValueObjects\EventRegistrationId;
use Modules\EventRegistrations\EventRegistrationsServiceProvider;
use Modules\EventRegistrations\Notifications\AdminNewRegistrationNotification;
use Modules\EventRegistrations\Notifications\EventReminderNotification;
use Modules\EventRegistrations\Notifications\RegistrationCancelledNotification;
use Modules\EventRegistrations\Notifications\RegistrationConfirmedNotification;
use Modules\EventRegistrations\Notifications\RegistrationRejectedNotification;
use Modules\EventRegistrations\Notifications\UserRegisteredNotification;
use Modules\EventRegistrations\Notifications\WaitingListPromotedNotification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Modules\ModuleTestCase;

final class SiteNotificationsTest extends ModuleTestCase
{
    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Publishing an event also announces it in everybody's bell (core#613): these tests
        // count only the module's own notices
        Queue::fake([AnnounceSiteNewsJob::class]);
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function accountNotifications(): iterable
    {
        yield 'registration' => [UserRegisteredNotification::class];
        yield 'confirmation' => [RegistrationConfirmedNotification::class];
        yield 'promotion' => [WaitingListPromotedNotification::class];
        yield 'cancellation' => [RegistrationCancelledNotification::class];
        yield 'rejection' => [RegistrationRejectedNotification::class];
        yield 'reminder' => [EventReminderNotification::class];
    }

    public function test_both_categories_show_notices_on_the_site(): void
    {
        $registry = app(MailCategoryRegistryInterface::class);

        $this->assertTrue($registry->find(EventRegistrationsServiceProvider::MAIL_REGISTRATIONS)?->site);
        $this->assertTrue($registry->find(EventRegistrationsServiceProvider::MAIL_REMINDERS)?->site);
    }

    /**
     * @param  class-string  $class
     */
    #[DataProvider('accountNotifications')]
    public function test_a_notice_is_stored_and_links_to_the_event(string $class): void
    {
        $user = UserModel::factory()->create();
        $event = EventModel::factory()->published()->create(['slug' => 'torneo-de-otono']);

        $user->notifyNow($this->make($class, $event, $user));

        $items = app(SiteNotificationServiceInterface::class)->latest($user, 10);
        $this->assertCount(1, $items);
        $this->assertSame('/eventos/torneo-de-otono', $items[0]->url);
        $this->assertStringContainsString($event->title, $items[0]->title);
    }

    /**
     * @param  class-string  $class
     */
    #[DataProvider('accountNotifications')]
    public function test_nothing_is_stored_when_the_event_is_no_longer_published(string $class): void
    {
        $user = UserModel::factory()->create();
        $event = EventModel::factory()->create(['is_published' => false]);

        $user->notifyNow($this->make($class, $event, $user));

        $this->assertSame(0, app(SiteNotificationServiceInterface::class)->unreadCount($user));
    }

    /**
     * @param  class-string  $class
     */
    #[DataProvider('accountNotifications')]
    public function test_the_subject_is_the_event(string $class): void
    {
        $user = UserModel::factory()->create();
        $event = EventModel::factory()->published()->create();

        $notification = $this->make($class, $event, $user);

        $this->assertContains(HasSiteNotification::CHANNEL, $notification->via($user));
        $subject = $notification->mailSubject();
        $this->assertSame('event-registrations.event', $subject->type);
        $this->assertSame((string) $event->id, $subject->id);
    }

    public function test_deleting_the_event_forgets_its_notices(): void
    {
        $user = UserModel::factory()->create();
        $event = EventModel::factory()->published()->create();
        $other = EventModel::factory()->published()->create();
        $user->notifyNow($this->make(EventReminderNotification::class, $event, $user));
        $user->notifyNow($this->make(EventReminderNotification::class, $other, $user));
        $service = app(SiteNotificationServiceInterface::class);
        $this->assertSame(2, $service->unreadCount($user));

        $event->delete();

        $this->assertSame(1, $service->unreadCount($user));
    }

    public function test_the_mailbox_notification_stays_mail_only(): void
    {
        $this->assertNotContains(HasSiteNotification::class, class_implements(AdminNewRegistrationNotification::class) ?: []);
    }

    private function make(string $class, EventModel $event, UserModel $user): EventReminderNotification|RegistrationCancelledNotification|RegistrationConfirmedNotification|RegistrationRejectedNotification|UserRegisteredNotification|WaitingListPromotedNotification
    {
        $registration = new EventRegistration(
            id: EventRegistrationId::generate(),
            eventId: (string) $event->id,
            userId: (string) $user->id,
            state: RegistrationState::Confirmed,
        );

        return match ($class) {
            EventReminderNotification::class => new EventReminderNotification($event),
            RegistrationCancelledNotification::class => new RegistrationCancelledNotification($event, $registration),
            RegistrationConfirmedNotification::class => new RegistrationConfirmedNotification($event, $registration),
            RegistrationRejectedNotification::class => new RegistrationRejectedNotification($event, $registration),
            UserRegisteredNotification::class => new UserRegisteredNotification($event, $registration),
            default => new WaitingListPromotedNotification($event, $registration),
        };
    }
}
