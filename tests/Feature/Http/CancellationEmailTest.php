<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Feature\Http;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationConfigModel;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationModel;
use Modules\EventRegistrations\Notifications\RegistrationCancelledNotification;
use Tests\Support\Modules\ModuleTestCase;

/**
 * A user who cancels their registration gets a confirmation email (send_cancellation_email,
 * on by default like the other emails).
 */
final class CancellationEmailTest extends ModuleTestCase
{
    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('es');
        Notification::fake();
        // Away from the nights the clocks change (RegistrationAcrossDaylightSavingTest)
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00'));
    }

    public function test_cancelling_a_registration_emails_the_user_with_a_link_to_the_event(): void
    {
        [$event, $user] = $this->registered(RegistrationState::Confirmed);

        $this->actingAs($user)->deleteJson("/eventos/{$event->id}/inscripcion")->assertOk();

        Notification::assertSentTo($user, RegistrationCancelledNotification::class, function (RegistrationCancelledNotification $n) use ($user, $event): bool {
            $message = $n->toMail($user);

            return $message->actionUrl === url('/eventos/'.$event->slug)
                && str_contains((string) $message->subject, $event->title);
        });
    }

    public function test_leaving_the_waiting_list_also_confirms_by_email(): void
    {
        [$event, $user] = $this->registered(RegistrationState::WaitingList, position: 1);

        $this->actingAs($user)->deleteJson("/eventos/{$event->id}/inscripcion")->assertOk();

        Notification::assertSentTo($user, RegistrationCancelledNotification::class);
    }

    public function test_no_email_when_the_setting_is_off(): void
    {
        config()->set('modules.settings.event-registrations.send_cancellation_email', false);
        [$event, $user] = $this->registered(RegistrationState::Confirmed);

        $this->actingAs($user)->deleteJson("/eventos/{$event->id}/inscripcion")->assertOk();

        Notification::assertNotSentTo($user, RegistrationCancelledNotification::class);
    }

    public function test_a_refused_cancellation_sends_nothing(): void
    {
        [$event, $user] = $this->registered(RegistrationState::Confirmed, ['cancellation_deadline' => now()->subHour()]);

        $this->actingAs($user)->deleteJson("/eventos/{$event->id}/inscripcion")->assertUnprocessable();

        Notification::assertNotSentTo($user, RegistrationCancelledNotification::class);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{EventModel, UserModel}
     */
    private function registered(RegistrationState $state, array $config = [], ?int $position = null): array
    {
        $event = EventModel::factory()->published()->create([
            'start_date' => now()->addDays(3),
            'end_date' => now()->addDays(3)->addHours(4),
        ]);

        EventRegistrationConfigModel::create([
            'event_id' => $event->id,
            'registration_enabled' => true,
            ...$config,
        ]);

        $user = UserModel::factory()->create();

        EventRegistrationModel::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'state' => $state,
            'position' => $position,
            'confirmed_at' => $state === RegistrationState::Confirmed ? now() : null,
        ]);

        return [$event, $user];
    }
}
