<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Feature\Mail;

use App\Application\Mail\Contracts\HasMailCategory;
use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\Mail\Services\MailPreferenceServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Modules\EventRegistrations\EventRegistrationsServiceProvider;
use Modules\EventRegistrations\Notifications\AdminNewRegistrationNotification;
use Modules\EventRegistrations\Notifications\EventReminderNotification;
use Modules\EventRegistrations\Notifications\RegistrationCancelledNotification;
use Modules\EventRegistrations\Notifications\RegistrationConfirmedNotification;
use Modules\EventRegistrations\Notifications\RegistrationRejectedNotification;
use Modules\EventRegistrations\Notifications\UserRegisteredNotification;
use Modules\EventRegistrations\Notifications\WaitingListPromotedNotification;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\Support\Modules\ModuleTestCase;

final class MailCategoriesTest extends ModuleTestCase
{
    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    /**
     * @return iterable<string, array{class-string, string}>
     */
    public static function accountNotifications(): iterable
    {
        yield 'registration' => [UserRegisteredNotification::class, EventRegistrationsServiceProvider::MAIL_REGISTRATIONS];
        yield 'confirmation' => [RegistrationConfirmedNotification::class, EventRegistrationsServiceProvider::MAIL_REGISTRATIONS];
        yield 'waiting list promotion' => [WaitingListPromotedNotification::class, EventRegistrationsServiceProvider::MAIL_REGISTRATIONS];
        yield 'cancellation' => [RegistrationCancelledNotification::class, EventRegistrationsServiceProvider::MAIL_REGISTRATIONS];
        yield 'rejection' => [RegistrationRejectedNotification::class, EventRegistrationsServiceProvider::MAIL_REGISTRATIONS];
        yield 'reminder' => [EventReminderNotification::class, EventRegistrationsServiceProvider::MAIL_REMINDERS];
    }

    /**
     * @param  class-string  $class
     */
    #[DataProvider('accountNotifications')]
    public function test_every_notification_to_an_account_has_a_registered_category(string $class, string $key): void
    {
        $notification = (new ReflectionClass($class))->newInstanceWithoutConstructor();

        $this->assertInstanceOf(HasMailCategory::class, $notification);
        $this->assertSame($key, $notification->mailCategory());
        $this->assertSame($key, app(MailCategoryRegistryInterface::class)->forNotification($notification)?->key);
    }

    public function test_the_notification_to_the_event_mailbox_has_no_category(): void
    {
        $this->assertFalse(is_subclass_of(AdminNewRegistrationNotification::class, HasMailCategory::class));
    }

    public function test_registrations_are_mandatory_and_reminders_optional(): void
    {
        $registry = app(MailCategoryRegistryInterface::class);

        $registrations = $registry->find(EventRegistrationsServiceProvider::MAIL_REGISTRATIONS);
        $this->assertTrue($registrations?->isMandatory());
        $this->assertNotNull($registrations->mandatoryReason);
        $this->assertFalse($registry->find(EventRegistrationsServiceProvider::MAIL_REMINDERS)?->isMandatory());
    }

    public function test_texts_are_translated(): void
    {
        app()->setLocale('es');
        $sections = app(MailPreferenceServiceInterface::class)->sectionsFor(UserModel::factory()->create());

        $labels = [];
        foreach ($sections as $section) {
            foreach ($section->categories as $category) {
                if (str_starts_with($category->key, 'event-registrations.')) {
                    $labels[$category->key] = $category->label;
                    $this->assertStringNotContainsString('event-registrations::', $category->label.$category->description);
                }
            }
        }

        $this->assertSame([
            EventRegistrationsServiceProvider::MAIL_REGISTRATIONS => 'Mis inscripciones',
            EventRegistrationsServiceProvider::MAIL_REMINDERS => 'Recordatorios de eventos',
        ], $labels);
    }

    public function test_a_reminder_the_person_turned_off_is_not_sent(): void
    {
        $sent = $this->countSent();
        $user = UserModel::factory()->create();
        $event = EventModel::factory()->published()->create();
        app(MailPreferenceServiceInterface::class)->update($user, [EventRegistrationsServiceProvider::MAIL_REMINDERS => false]);

        $user->notifyNow(new EventReminderNotification($event));

        $this->assertSame(0, $sent->count);
    }

    public function test_a_reminder_goes_out_when_the_person_keeps_it_on(): void
    {
        $sent = $this->countSent();
        $user = UserModel::factory()->create();

        $user->notifyNow(new EventReminderNotification(EventModel::factory()->published()->create()));

        $this->assertSame(1, $sent->count);
    }

    private function countSent(): object
    {
        $counter = new class
        {
            public int $count = 0;
        };
        Event::listen(NotificationSent::class, function () use ($counter): void {
            $counter->count++;
        });

        return $counter;
    }
}
