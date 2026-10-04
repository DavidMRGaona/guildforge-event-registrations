<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Unit\Notifications;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Modules\EventRegistrations\Domain\Entities\EventRegistration;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Domain\ValueObjects\EventRegistrationId;
use Modules\EventRegistrations\Notifications\UserRegisteredNotification;
use Tests\TestCase;

final class UserRegisteredNotificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['translator']->addNamespace(
            'event-registrations',
            base_path('modules/event-registrations/lang'),
        );
    }

    public function test_confirmed_registration_uses_confirmed_subject(): void
    {
        $this->assertSame(
            __('event-registrations::messages.emails.confirmed_subject', ['event' => 'Torneo']),
            $this->subjectFor(RegistrationState::Confirmed),
        );
    }

    public function test_pending_registration_uses_received_subject(): void
    {
        $this->assertSame(
            __('event-registrations::messages.emails.registered_subject', ['event' => 'Torneo']),
            $this->subjectFor(RegistrationState::Pending),
        );
    }

    public function test_waiting_list_registration_uses_waiting_list_subject(): void
    {
        $this->assertSame(
            __('event-registrations::messages.emails.waiting_list_subject', ['event' => 'Torneo']),
            $this->subjectFor(RegistrationState::WaitingList, position: 2),
        );
    }

    private function subjectFor(RegistrationState $state, ?int $position = null): string
    {
        $event = new EventModel;
        $event->title = 'Torneo';
        $event->slug = 'torneo';
        $event->start_date = now()->addWeek();

        $registration = new EventRegistration(
            id: EventRegistrationId::generate(),
            eventId: 'event-1',
            userId: 'user-1',
            state: $state,
            position: $position,
        );

        $user = new UserModel;
        $user->name = 'Ana';

        return (new UserRegisteredNotification($event, $registration))->toMail($user)->subject;
    }
}
