<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Feature\Notifications;

use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\SiteNotifications\Contracts\HasSiteNotification;
use App\Application\SiteNotifications\DTOs\SiteNotificationItemDTO;
use App\Application\SiteNotifications\Services\SiteNotificationServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Modules\EventRegistrations\Domain\Entities\EventRegistration;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Domain\Events\UserRegisteredToEvent;
use Modules\EventRegistrations\Domain\Repositories\EventRegistrationRepositoryInterface;
use Modules\EventRegistrations\Domain\ValueObjects\EventRegistrationId;
use Modules\EventRegistrations\EventRegistrationsServiceProvider;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationConfigModel;
use Modules\EventRegistrations\Notifications\AdminNewRegistrationNotification;
use Modules\EventRegistrations\Notifications\NewRegistrationToManageNotification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Whoever manages registrations hears of a new one in the bell (event-registrations#114), on top of
 * the mail to the address saved in the event, which stays as it was.
 */
final class ManagerSiteNotificationsTest extends ModuleTestCase
{
    use CreatesUsersWithPermissions;
    use InteractsWithModulePanel;

    private const array MANAGER_PERMISSIONS = [
        'events.update',
        'event-registrations:registrations.view_any',
        'event-registrations:registrations.update',
    ];

    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel();
    }

    public function test_the_management_category_is_optional_and_only_on_the_site(): void
    {
        $category = app(MailCategoryRegistryInterface::class)->find(EventRegistrationsServiceProvider::MAIL_MANAGEMENT);

        $this->assertNotNull($category);
        $this->assertNull($category->mandatoryReason);
        $this->assertTrue($category->site);
        $this->assertFalse($category->mail);
    }

    public function test_only_whoever_manages_registrations_sees_the_category(): void
    {
        $category = app(MailCategoryRegistryInterface::class)->find(EventRegistrationsServiceProvider::MAIL_MANAGEMENT);
        $this->assertNotNull($category?->audience);

        $this->assertTrue(($category->audience)($this->editorWithPermissions(self::MANAGER_PERMISSIONS)));
        $this->assertFalse(($category->audience)(UserModel::factory()->create()));
    }

    public function test_the_notice_goes_only_to_the_site(): void
    {
        $notification = $this->notification(EventModel::factory()->create(), UserModel::factory()->create());
        $manager = $this->editorWithPermissions(self::MANAGER_PERMISSIONS);

        $this->assertSame([HasSiteNotification::CHANNEL], $notification->via($manager));
        $this->assertSame(EventRegistrationsServiceProvider::MAIL_MANAGEMENT, $notification->mailCategory());
    }

    public function test_a_new_registration_reaches_the_manager_and_links_to_the_registrations_tab(): void
    {
        $manager = $this->editorWithPermissions(self::MANAGER_PERMISSIONS);
        $event = $this->upcomingEvent(['title' => 'Jornada de rol', 'slug' => 'jornada-de-rol']);
        $ana = UserModel::factory()->create(['name' => 'Ana', 'display_name' => null]);

        $this->register($event, $ana, RegistrationState::Pending);

        $items = app(SiteNotificationServiceInterface::class)->latest($manager, 10);
        $this->assertCount(1, $items);
        $this->assertSame('Ana se inscribió en «Jornada de rol»', $items[0]->title);
        $this->assertSame('Estado: Pendiente', $items[0]->body);
        $this->assertStringStartsWith('/admin/', $items[0]->url);
        $this->assertStringContainsString('jornada-de-rol/edit', $items[0]->url);
        $this->assertStringContainsString('relation=', $items[0]->url);
    }

    public function test_the_waiting_list_position_is_in_the_body(): void
    {
        $manager = $this->editorWithPermissions(self::MANAGER_PERMISSIONS);
        $event = $this->upcomingEvent();

        $this->register($event, UserModel::factory()->create(), RegistrationState::WaitingList, position: 3);

        $items = app(SiteNotificationServiceInterface::class)->latest($manager, 10);
        $this->assertSame('Estado: Lista de espera (puesto 3)', $items[0]->body);
    }

    public function test_unread_registrations_to_one_event_are_one_notice(): void
    {
        $manager = $this->editorWithPermissions(self::MANAGER_PERMISSIONS);
        $event = $this->upcomingEvent(['title' => 'Jornada de rol']);
        $other = $this->upcomingEvent(['title' => 'Torneo de otoño']);

        foreach (['Ana', 'Bea', 'Carla'] as $name) {
            $this->register($event, UserModel::factory()->create(['name' => $name, 'display_name' => null]), RegistrationState::Confirmed);
        }
        $this->register($other, UserModel::factory()->create(), RegistrationState::Confirmed);

        $items = app(SiteNotificationServiceInterface::class)->latest($manager, 10);
        $this->assertCount(2, $items);
        $grouped = collect($items)->first(fn (SiteNotificationItemDTO $item): bool => str_contains($item->title, 'Jornada de rol'));
        $this->assertNotNull($grouped);
        $this->assertSame('Carla y 2 más se inscribieron en «Jornada de rol»', $grouped->title);
    }

    public function test_the_display_name_is_preferred(): void
    {
        $manager = $this->editorWithPermissions(self::MANAGER_PERMISSIONS);
        $event = $this->upcomingEvent(['title' => 'Jornada de rol']);

        $this->register($event, UserModel::factory()->create(['name' => 'Ana López', 'display_name' => 'Ana']), RegistrationState::Confirmed);

        $this->assertSame('Ana se inscribió en «Jornada de rol»', app(SiteNotificationServiceInterface::class)->latest($manager, 1)[0]->title);
    }

    /**
     * @return iterable<string, array{list<string>}>
     */
    public static function incompletePermissions(): iterable
    {
        yield 'cannot change registrations' => [['events.update', 'event-registrations:registrations.view_any']];
        yield 'cannot open the event in the panel' => [['event-registrations:registrations.view_any', 'event-registrations:registrations.update']];
        yield 'cannot see the registrations tab' => [['events.update', 'event-registrations:registrations.update']];
    }

    /**
     * @param  list<string>  $permissions
     */
    #[DataProvider('incompletePermissions')]
    public function test_nobody_gets_a_link_they_cannot_open(array $permissions): void
    {
        $holder = $this->editorWithPermissions($permissions);
        $event = $this->upcomingEvent();

        $holder->notifyNow($this->notification($event, UserModel::factory()->create()));

        $this->assertSame(0, app(SiteNotificationServiceInterface::class)->unreadCount($holder));
    }

    public function test_an_account_without_panel_access_gets_nothing(): void
    {
        $user = UserModel::factory()->create();

        $user->notifyNow($this->notification($this->upcomingEvent(), UserModel::factory()->create()));

        $this->assertSame(0, app(SiteNotificationServiceInterface::class)->unreadCount($user));
    }

    public function test_a_manager_who_signs_up_is_not_told_about_it(): void
    {
        Notification::fake();
        $manager = $this->editorWithPermissions(self::MANAGER_PERMISSIONS);
        $otherManager = $this->editorWithPermissions(self::MANAGER_PERMISSIONS);

        // They still get their own notice as an attendee: only the one for managers is left out
        $this->register($this->upcomingEvent(), $manager, RegistrationState::Confirmed);

        Notification::assertNotSentTo($manager, NewRegistrationToManageNotification::class);
        Notification::assertSentTo($otherManager, NewRegistrationToManageNotification::class);
    }

    public function test_nothing_is_stored_when_the_event_is_gone_before_delivery(): void
    {
        $manager = $this->editorWithPermissions(self::MANAGER_PERMISSIONS);
        $event = $this->upcomingEvent();
        $notification = $this->notification($event, UserModel::factory()->create());
        $event->delete();

        $manager->notifyNow($notification);

        $this->assertSame(0, app(SiteNotificationServiceInterface::class)->unreadCount($manager));
    }

    public function test_a_past_event_tells_nobody(): void
    {
        $manager = $this->editorWithPermissions(self::MANAGER_PERMISSIONS);
        $event = EventModel::factory()->published()->create([
            'start_date' => now()->subWeek(),
            'end_date' => now()->subWeek()->addHours(4),
        ]);

        $this->register($event, UserModel::factory()->create(), RegistrationState::Confirmed);

        $this->assertSame(0, app(SiteNotificationServiceInterface::class)->unreadCount($manager));
    }

    public function test_deleting_the_event_forgets_the_notices(): void
    {
        $manager = $this->editorWithPermissions(self::MANAGER_PERMISSIONS);
        $event = $this->upcomingEvent();
        $this->register($event, UserModel::factory()->create(), RegistrationState::Confirmed);
        $service = app(SiteNotificationServiceInterface::class);
        $this->assertSame(1, $service->unreadCount($manager));

        $event->delete();

        $this->assertSame(0, $service->unreadCount($manager));
    }

    public function test_the_mailbox_still_gets_its_mail_and_managers_their_notice(): void
    {
        Notification::fake();
        $manager = $this->editorWithPermissions(self::MANAGER_PERMISSIONS);
        $event = $this->upcomingEvent();
        EventRegistrationConfigModel::query()->updateOrCreate(['event_id' => $event->id], [
            'registration_enabled' => true,
            'notification_email' => 'organisers@example.test',
        ]);

        $this->register($event, UserModel::factory()->create(), RegistrationState::Confirmed);

        Notification::assertSentOnDemand(AdminNewRegistrationNotification::class);
        Notification::assertSentTo($manager, NewRegistrationToManageNotification::class);
    }

    public function test_managers_are_told_without_a_mailbox_in_the_event(): void
    {
        Notification::fake();
        $manager = $this->editorWithPermissions(self::MANAGER_PERMISSIONS);

        $this->register($this->upcomingEvent(), UserModel::factory()->create(), RegistrationState::Confirmed);

        Notification::assertSentOnDemandTimes(AdminNewRegistrationNotification::class, 0);
        Notification::assertSentTo($manager, NewRegistrationToManageNotification::class);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function upcomingEvent(array $attributes = []): EventModel
    {
        return EventModel::factory()->published()->create([
            'start_date' => now()->addWeek(),
            'end_date' => now()->addWeek()->addHours(4),
            ...$attributes,
        ]);
    }

    private function register(EventModel $event, UserModel $user, RegistrationState $state, ?int $position = null): void
    {
        $registration = new EventRegistration(
            id: EventRegistrationId::generate(),
            eventId: (string) $event->id,
            userId: (string) $user->id,
            state: $state,
            position: $position,
        );
        app(EventRegistrationRepositoryInterface::class)->save($registration);

        Event::dispatch(UserRegisteredToEvent::create(
            registrationId: $registration->id()->value,
            eventId: (string) $event->id,
            userId: (string) $user->id,
            state: $state,
            position: $position,
        ));
    }

    private function notification(EventModel $event, UserModel $registrant): NewRegistrationToManageNotification
    {
        return new NewRegistrationToManageNotification(
            eventId: (string) $event->id,
            registrationId: EventRegistrationId::generate()->value,
            registrantId: (string) $registrant->id,
            registrantName: $registrant->name,
            state: RegistrationState::Confirmed,
            position: null,
        );
    }
}
