<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Feature\Http;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationConfigModel;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationModel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The five routes of routes/web.php, with and without a session: the public status, the
 * signed-in user's registration (read, create, cancel) and the list of their registrations.
 */
final class RegistrationRoutesTest extends ModuleTestCase
{
    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    private EventModel $event;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->event = EventModel::factory()->published()->create(['start_date' => now()->addWeek()]);
        EventRegistrationConfigModel::create([
            'event_id' => $this->event->id,
            'registration_enabled' => true,
        ]);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function routeProvider(): array
    {
        return [
            'status' => ['GET', '/eventos/{event}/inscripcion/estado', 'event-registrations.status'],
            'show' => ['GET', '/eventos/{event}/inscripcion', 'event-registrations.show'],
            'store' => ['POST', '/eventos/{event}/inscripcion', 'event-registrations.store'],
            'destroy' => ['DELETE', '/eventos/{event}/inscripcion', 'event-registrations.destroy'],
            'my registrations' => ['GET', '/mis-inscripciones', 'event-registrations.my-registrations'],
        ];
    }

    #[DataProvider('routeProvider')]
    public function test_the_route_is_registered_under_its_name(string $method, string $uri, string $name): void
    {
        $route = Route::getRoutes()->getByName($name);

        $this->assertNotNull($route);
        $this->assertContains($method, $route->methods());
        $this->assertSame(ltrim(str_replace('{event}', '{eventId}', $uri), '/'), $route->uri());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function authenticatedRouteProvider(): array
    {
        return [
            'show' => ['GET', '/eventos/{event}/inscripcion'],
            'store' => ['POST', '/eventos/{event}/inscripcion'],
            'destroy' => ['DELETE', '/eventos/{event}/inscripcion'],
            'my registrations' => ['GET', '/mis-inscripciones'],
        ];
    }

    #[DataProvider('authenticatedRouteProvider')]
    public function test_a_json_request_without_a_session_gets_a_401(string $method, string $uri): void
    {
        $this->json($method, $this->url($uri))->assertUnauthorized();

        $this->assertSame(0, EventRegistrationModel::query()->count());
    }

    public function test_a_browser_visit_to_my_registrations_without_a_session_goes_to_the_login_page(): void
    {
        $this->get('/mis-inscripciones')->assertRedirect(route('login'));
    }

    public function test_the_status_is_public(): void
    {
        $this->getJson($this->url('/eventos/{event}/inscripcion/estado'))
            ->assertOk()
            ->assertJsonPath('data.event_id', $this->event->id)
            ->assertJsonPath('data.registration_enabled', true)
            ->assertJsonPath('data.current_participants', 0)
            ->assertJsonPath('data.meets_members_only', true);
    }

    public function test_the_status_with_a_session_counts_confirmed_participants(): void
    {
        $user = UserModel::factory()->create();
        $this->createRegistration($user, RegistrationState::Confirmed);

        $this->actingAs($user)
            ->getJson($this->url('/eventos/{event}/inscripcion/estado'))
            ->assertOk()
            ->assertJsonPath('data.current_participants', 1)
            ->assertJsonPath('data.meets_members_only', true);
    }

    public function test_show_returns_null_when_the_user_is_not_registered(): void
    {
        $this->actingAs(UserModel::factory()->create())
            ->getJson($this->url('/eventos/{event}/inscripcion'))
            ->assertOk()
            ->assertExactJson(['data' => null]);
    }

    public function test_a_user_cancelling_from_the_waiting_list_moves_the_others_up(): void
    {
        $this->createRegistration(UserModel::factory()->create(), RegistrationState::Confirmed);
        $leaving = UserModel::factory()->create();
        $this->createRegistration($leaving, RegistrationState::WaitingList)->update(['position' => 1]);
        $behind = $this->createRegistration(UserModel::factory()->create(), RegistrationState::WaitingList);
        $behind->update(['position' => 2]);

        $this->actingAs($leaving)
            ->deleteJson($this->url('/eventos/{event}/inscripcion'))
            ->assertOk();

        $this->assertSame(1, $behind->fresh()?->position);
    }

    public function test_show_returns_the_users_registration(): void
    {
        $user = UserModel::factory()->create();
        $registration = $this->createRegistration($user, RegistrationState::Confirmed);

        $this->actingAs($user)
            ->getJson($this->url('/eventos/{event}/inscripcion'))
            ->assertOk()
            ->assertJsonPath('data.id', $registration->id)
            ->assertJsonPath('data.event_id', $this->event->id)
            ->assertJsonPath('data.state', 'confirmed');
    }

    public function test_store_registers_the_user(): void
    {
        $user = UserModel::factory()->create();

        $this->actingAs($user)
            ->postJson($this->url('/eventos/{event}/inscripcion'), ['notes' => 'Llevo dados'])
            ->assertCreated()
            ->assertJsonPath('data.event_id', $this->event->id)
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.state', 'confirmed')
            ->assertJsonPath('message', __('event-registrations::messages.success.registered'));

        $this->assertDatabaseHas('event_registrations_registrations', [
            'event_id' => $this->event->id,
            'user_id' => $user->id,
            'notes' => 'Llevo dados',
        ]);
    }

    public function test_destroy_cancels_the_users_registration(): void
    {
        $user = UserModel::factory()->create();
        $registration = $this->createRegistration($user, RegistrationState::Confirmed);

        $this->actingAs($user)
            ->deleteJson($this->url('/eventos/{event}/inscripcion'))
            ->assertOk()
            ->assertJsonPath('message', __('event-registrations::messages.success.cancelled'));

        $this->assertSame(RegistrationState::Cancelled, $registration->fresh()?->state);
    }

    public function test_destroy_without_a_registration_is_a_404(): void
    {
        $this->actingAs(UserModel::factory()->create())
            ->deleteJson($this->url('/eventos/{event}/inscripcion'))
            ->assertNotFound()
            ->assertJsonPath('message', __('event-registrations::messages.errors.not_found'));
    }

    public function test_my_registrations_lists_only_the_users_registrations(): void
    {
        $user = UserModel::factory()->create();
        $registration = $this->createRegistration($user, RegistrationState::Confirmed);
        $this->createRegistration(UserModel::factory()->create(), RegistrationState::Confirmed);

        $this->actingAs($user)
            ->getJson('/mis-inscripciones')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $registration->id)
            ->assertJsonPath('data.0.event_id', $this->event->id)
            ->assertJsonPath('data.0.state', 'confirmed');
    }

    public function test_my_registrations_is_empty_for_a_user_without_registrations(): void
    {
        $this->actingAs(UserModel::factory()->create())
            ->getJson('/mis-inscripciones')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_an_event_id_that_is_not_a_uuid_does_not_match_any_route(): void
    {
        $this->getJson('/eventos/not-a-uuid/inscripcion/estado')->assertNotFound();
    }

    private function url(string $uri): string
    {
        return str_replace('{event}', $this->event->id, $uri);
    }

    private function createRegistration(UserModel $user, RegistrationState $state): EventRegistrationModel
    {
        return EventRegistrationModel::create([
            'event_id' => $this->event->id,
            'user_id' => $user->id,
            'state' => $state,
            'confirmed_at' => $state === RegistrationState::Confirmed ? now() : null,
        ]);
    }
}
