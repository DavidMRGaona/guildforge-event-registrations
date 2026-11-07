<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Feature\Http;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Notification;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationConfigModel;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * After cancelling, a person may register again from the web; a registration the organisers
 * rejected may not be requested again, by the server or by the button.
 */
final class RegisterAgainTest extends ModuleTestCase
{
    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    private const string COMPONENT = __DIR__.'/../../../resources/js/components/RegistrationButton.vue';

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
            'requires_confirmation' => false,
        ]);
        $this->user = UserModel::factory()->create();
    }

    public function test_a_cancelled_registration_can_register_again(): void
    {
        $registration = $this->createRegistration(RegistrationState::Cancelled);

        $this->actingAs($this->user)
            ->postJson("/eventos/{$this->event->id}/inscripcion")
            ->assertCreated()
            ->assertJsonPath('data.id', $registration->id)
            ->assertJsonPath('data.state', RegistrationState::Confirmed->value);
    }

    public function test_a_rejected_registration_cannot_be_requested_again(): void
    {
        $registration = $this->createRegistration(RegistrationState::Rejected);

        $this->actingAs($this->user)
            ->postJson("/eventos/{$this->event->id}/inscripcion")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Tu inscripción en este evento fue rechazada. Si crees que es un error, contacta con la organización.');

        $this->assertSame(RegistrationState::Rejected, $registration->fresh()?->state);
    }

    public function test_a_rejected_registration_cannot_join_the_waiting_list_either(): void
    {
        EventRegistrationConfigModel::query()->whereKey($this->event->id)->update(['max_participants' => 1, 'waiting_list_enabled' => true]);
        EventRegistrationModel::create([
            'event_id' => $this->event->id,
            'user_id' => UserModel::factory()->create()->id,
            'state' => RegistrationState::Confirmed,
            'confirmed_at' => now(),
        ]);
        $registration = $this->createRegistration(RegistrationState::Rejected);

        $this->actingAs($this->user)
            ->postJson("/eventos/{$this->event->id}/inscripcion")
            ->assertUnprocessable();

        $fresh = $registration->fresh();
        $this->assertInstanceOf(EventRegistrationModel::class, $fresh);
        $this->assertSame(RegistrationState::Rejected, $fresh->state);
        $this->assertNull($fresh->position);
    }

    public function test_the_button_only_stays_disabled_for_a_rejected_registration(): void
    {
        $source = file_get_contents(self::COMPONENT);
        $this->assertIsString($source);

        if (preg_match('/const isUnavailable = computed\(\(\) => \{(.*?)\n\}\);/s', $source, $body) !== 1) {
            $this->fail('isUnavailable is not in the component');
        }

        $this->assertStringNotContainsString('if (registration.value) return true;', $body[1]);
        $this->assertStringContainsString('if (isRejected.value) return true;', $body[1]);
        $this->assertStringContainsString("const isRejected = computed(() => registration.value?.state === 'rejected');", $source);
        $this->assertMatchesRegularExpression('/const canRegister = computed\(\(\) => \{[^}]*if \(isRejected\.value\) return false;/s', $source);
        $this->assertMatchesRegularExpression('/const canJoinWaitingList = computed\(\(\) => \{[^}]*if \(isRejected\.value\) return false;/s', $source);
        $this->assertStringContainsString("if (isRejected.value) return t('eventRegistrationsRejection.button');", $source);
    }

    public function test_the_rejected_button_text_is_translated(): void
    {
        foreach (['es' => 'Inscripción rechazada', 'en' => 'Registration rejected'] as $locale => $text) {
            $translations = file_get_contents(__DIR__."/../../../resources/js/locales/{$locale}.ts");
            $this->assertIsString($translations);
            $this->assertMatchesRegularExpression("/eventRegistrationsRejection: \\{\\s*button: '{$text}',\\s*\\}/", $translations);
        }
    }

    private function createRegistration(RegistrationState $state): EventRegistrationModel
    {
        return EventRegistrationModel::create([
            'event_id' => $this->event->id,
            'user_id' => $this->user->id,
            'state' => $state,
            'cancelled_at' => $state === RegistrationState::Cancelled ? now() : null,
        ]);
    }
}
