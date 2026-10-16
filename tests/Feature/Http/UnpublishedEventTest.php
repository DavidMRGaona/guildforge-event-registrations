<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Feature\Http;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationConfigModel;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationModel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The registration routes of an event answer 404 when the event does not exist or is not
 * published, before looking at any date.
 */
final class UnpublishedEventTest extends ModuleTestCase
{
    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function eventRoutes(): array
    {
        return [
            'status' => ['GET', '/eventos/{event}/inscripcion/estado'],
            'show' => ['GET', '/eventos/{event}/inscripcion'],
            'store' => ['POST', '/eventos/{event}/inscripcion'],
            'destroy' => ['DELETE', '/eventos/{event}/inscripcion'],
        ];
    }

    #[DataProvider('eventRoutes')]
    public function test_an_event_that_does_not_exist_is_not_found(string $method, string $uri): void
    {
        $this->actingAs(UserModel::factory()->create())
            ->json($method, str_replace('{event}', (string) Str::uuid(), $uri))
            ->assertNotFound()
            ->assertJsonPath('message', __('event-registrations::messages.errors.event_not_found'));

        $this->assertSame(0, EventRegistrationModel::query()->count());
    }

    #[DataProvider('eventRoutes')]
    public function test_an_unpublished_event_is_not_found_even_for_a_registered_user(string $method, string $uri): void
    {
        $event = EventModel::factory()->draft()->create(['start_date' => now()->addWeek()]);
        EventRegistrationConfigModel::create(['event_id' => $event->id, 'registration_enabled' => true]);
        $user = UserModel::factory()->create();
        $registration = EventRegistrationModel::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'state' => RegistrationState::Confirmed,
            'confirmed_at' => now(),
        ]);

        $this->actingAs($user)
            ->json($method, str_replace('{event}', $event->id, $uri))
            ->assertNotFound()
            ->assertJsonPath('message', __('event-registrations::messages.errors.event_not_found'));

        $this->assertSame(1, EventRegistrationModel::query()->count());
        $this->assertSame(RegistrationState::Confirmed, $registration->fresh()?->state);
    }

    public function test_the_status_of_an_unpublished_event_is_not_found_for_guests(): void
    {
        $event = EventModel::factory()->draft()->create(['start_date' => now()->addWeek()]);

        $this->getJson("/eventos/{$event->id}/inscripcion/estado")
            ->assertNotFound()
            ->assertJsonPath('message', __('event-registrations::messages.errors.event_not_found'));
    }

    public function test_a_published_future_event_still_works(): void
    {
        $event = EventModel::factory()->published()->create(['start_date' => now()->addWeek()]);
        EventRegistrationConfigModel::create(['event_id' => $event->id, 'registration_enabled' => true]);
        $this->actingAs(UserModel::factory()->create());

        $this->getJson("/eventos/{$event->id}/inscripcion/estado")->assertOk()->assertJsonPath('data.is_open', true);
        $this->postJson("/eventos/{$event->id}/inscripcion")->assertCreated();
        $this->getJson("/eventos/{$event->id}/inscripcion")->assertOk()->assertJsonPath('data.state', 'confirmed');
        $this->deleteJson("/eventos/{$event->id}/inscripcion")->assertOk();
    }
}
