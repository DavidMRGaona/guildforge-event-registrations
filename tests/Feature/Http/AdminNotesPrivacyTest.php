<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Feature\Http;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationConfigModel;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The notes written in the panel about a registration are internal: no JSON a user receives
 * carries them, while the panel keeps reading and writing them on the registration.
 */
final class AdminNotesPrivacyTest extends ModuleTestCase
{
    private const string ADMIN_NOTES = 'Internal: pays at the door, watch out';

    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    private EventModel $event;

    private UserModel $user;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->event = EventModel::factory()->published()->create(['start_date' => now()->addWeek()]);
        EventRegistrationConfigModel::create([
            'event_id' => $this->event->id,
            'registration_enabled' => true,
        ]);
        $this->user = UserModel::factory()->create();
    }

    public function test_the_users_registration_for_an_event_does_not_carry_the_admin_notes(): void
    {
        $this->createRegistrationWithAdminNotes();

        $response = $this->actingAs($this->user)
            ->getJson("/eventos/{$this->event->id}/inscripcion")
            ->assertOk()
            ->assertJsonPath('data.state', 'confirmed');

        $this->assertArrayNotHasKey('admin_notes', $response->json('data'));
        $this->assertNotLeaked($response);
    }

    public function test_the_users_list_of_registrations_does_not_carry_the_admin_notes(): void
    {
        $this->createRegistrationWithAdminNotes();

        $response = $this->actingAs($this->user)
            ->getJson('/mis-inscripciones')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertArrayNotHasKey('admin_notes', $response->json('data.0'));
        $this->assertNotLeaked($response);
    }

    public function test_the_response_to_a_new_registration_does_not_carry_the_admin_notes(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("/eventos/{$this->event->id}/inscripcion")
            ->assertCreated();

        $this->assertArrayNotHasKey('admin_notes', $response->json('data'));
    }

    public function test_the_admin_notes_stay_on_the_registration(): void
    {
        $registration = $this->createRegistrationWithAdminNotes();

        $this->actingAs($this->user)->getJson('/mis-inscripciones')->assertOk();

        $this->assertSame(self::ADMIN_NOTES, $registration->fresh()?->admin_notes);
    }

    private function createRegistrationWithAdminNotes(): EventRegistrationModel
    {
        return EventRegistrationModel::create([
            'event_id' => $this->event->id,
            'user_id' => $this->user->id,
            'state' => RegistrationState::Confirmed,
            'confirmed_at' => now(),
            'admin_notes' => self::ADMIN_NOTES,
        ]);
    }

    /**
     * @param  TestResponse<\Symfony\Component\HttpFoundation\Response>  $response
     */
    private function assertNotLeaked(TestResponse $response): void
    {
        $this->assertStringNotContainsString(self::ADMIN_NOTES, (string) $response->getContent());
    }
}
