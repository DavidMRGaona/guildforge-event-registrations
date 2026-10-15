<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Feature\Http;

use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Closure;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationConfigModel;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationModel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Modules\ModuleTestCase;

/**
 * What the registration button and the "Mis inscripciones" tab get from the server when the
 * signed-in account must verify its email: the page flag useVerificationGate() reads
 * (auth.user.mustVerifyEmail) and the answers of the module's routes, requested the way the
 * components request them. Guests and accounts that need not verify keep today's behaviour.
 */
final class EmailVerificationGateTest extends ModuleTestCase
{
    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    private const string CUTOFF = '2026-01-01T00:00:00+00:00';

    private EventModel $event;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->withoutVite();

        app(SettingsServiceInterface::class)->set('email_verification_enforced_since', self::CUTOFF);

        $this->event = EventModel::factory()->published()->create([
            'slug' => 'partida-de-prueba',
            'start_date' => now()->addWeek(),
            'end_date' => now()->addWeek()->addHours(4),
        ]);
        EventRegistrationConfigModel::create([
            'event_id' => $this->event->id,
            'registration_enabled' => true,
        ]);
    }

    public function test_a_guest_gets_no_user_on_the_event_page_and_the_public_status(): void
    {
        $this->requireVerification();

        $this->get('/eventos/partida-de-prueba')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('auth.user', null));

        $this->getJson($this->url('/eventos/{event}/inscripcion/estado'))
            ->assertOk()
            ->assertJsonPath('data.registration_enabled', true)
            ->assertJsonPath('data.meets_members_only', true);
    }

    public function test_a_guest_cannot_register_and_is_not_sent_to_the_verification_notice(): void
    {
        $this->requireVerification();

        $this->postJson($this->url('/eventos/{event}/inscripcion'), ['form_data' => []])
            ->assertUnauthorized()
            ->assertHeaderMissing('X-Email-Verification-Required');

        $this->assertSame(0, EventRegistrationModel::query()->count());
    }

    /**
     * @return array<string, array{Closure(self): UserModel}>
     */
    public static function accountThatNeedNotVerifyProvider(): array
    {
        return [
            'verified account' => [function (self $test): UserModel {
                $test->requireVerification();

                return UserModel::factory()->create();
            }],
            'unverified account created before the cutoff' => [function (self $test): UserModel {
                $test->requireVerification();

                return UserModel::factory()->unverified()->create(['created_at' => '2025-06-01 12:00:00']);
            }],
            'unverified account with the setting off' => [
                fn (self $test): UserModel => UserModel::factory()->unverified()->create(),
            ],
        ];
    }

    /**
     * @param  Closure(self): UserModel  $account
     */
    #[DataProvider('accountThatNeedNotVerifyProvider')]
    public function test_an_account_that_need_not_verify_is_not_blocked_on_the_event_page(Closure $account): void
    {
        $this->actingAs($account($this))
            ->get('/eventos/partida-de-prueba')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('auth.user.mustVerifyEmail', false));
    }

    /**
     * @param  Closure(self): UserModel  $account
     */
    #[DataProvider('accountThatNeedNotVerifyProvider')]
    public function test_an_account_that_need_not_verify_reads_registers_and_cancels(Closure $account): void
    {
        $this->actingAs($account($this));

        $this->get($this->url('/eventos/{event}/inscripcion'))
            ->assertOk()
            ->assertExactJson(['data' => null]);

        $this->postJson($this->url('/eventos/{event}/inscripcion'), ['form_data' => []])
            ->assertCreated()
            ->assertJsonPath('data.state', RegistrationState::Confirmed->value);

        $this->deleteJson($this->url('/eventos/{event}/inscripcion'))
            ->assertOk();

        $this->assertSame(RegistrationState::Cancelled, EventRegistrationModel::query()->sole()->state);
    }

    /**
     * @param  Closure(self): UserModel  $account
     */
    #[DataProvider('accountThatNeedNotVerifyProvider')]
    public function test_an_account_that_need_not_verify_lists_its_registrations(Closure $account): void
    {
        $user = $account($this);
        $this->createRegistration($user, RegistrationState::Confirmed);

        $this->actingAs($user)
            ->getJson('/mis-inscripciones')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.event.title', $this->event->title);
    }

    public function test_an_account_that_must_verify_is_flagged_on_the_event_page(): void
    {
        $this->actingAs($this->mustVerifyUser())
            ->get('/eventos/partida-de-prueba')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('auth.user.mustVerifyEmail', true));
    }

    public function test_an_account_that_must_verify_is_flagged_on_the_profile_page(): void
    {
        $this->actingAs($this->mustVerifyUser())
            ->get('/perfil')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Profile/Show')
                ->where('auth.user.mustVerifyEmail', true)
            );
    }

    public function test_an_account_that_must_verify_still_gets_the_public_status(): void
    {
        $this->actingAs($this->mustVerifyUser())
            ->getJson($this->url('/eventos/{event}/inscripcion/estado'))
            ->assertOk()
            ->assertJsonPath('data.registration_enabled', true)
            ->assertJsonPath('data.is_open', true)
            ->assertJsonPath('data.meets_members_only', true);
    }

    public function test_the_registration_of_an_account_that_must_verify_gets_the_flagged_403(): void
    {
        $this->actingAs($this->mustVerifyUser())
            ->postJson($this->url('/eventos/{event}/inscripcion'), ['form_data' => []])
            ->assertForbidden()
            ->assertHeader('X-Email-Verification-Required', '1')
            ->assertJsonPath('error', 'email_verification_required');

        $this->assertSame(0, EventRegistrationModel::query()->count());
    }

    public function test_the_cancellation_of_an_account_that_must_verify_gets_the_flagged_403(): void
    {
        $user = $this->mustVerifyUser();
        $this->createRegistration($user, RegistrationState::Confirmed);

        $this->actingAs($user)
            ->deleteJson($this->url('/eventos/{event}/inscripcion'))
            ->assertForbidden()
            ->assertHeader('X-Email-Verification-Required', '1')
            ->assertJsonPath('error', 'email_verification_required');

        $this->assertSame(RegistrationState::Confirmed, EventRegistrationModel::query()->sole()->state);
    }

    /**
     * RegistrationButton reads the registration without `Accept: application/json`, so the
     * request follows the redirect to the notice (what the gate recognises as "must verify").
     */
    public function test_reading_the_registration_of_an_account_that_must_verify_goes_to_the_notice(): void
    {
        $this->actingAs($this->mustVerifyUser())
            ->get($this->url('/eventos/{event}/inscripcion'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_the_registrations_list_of_an_account_that_must_verify_gets_the_flagged_403(): void
    {
        $user = $this->mustVerifyUser();
        $this->createRegistration($user, RegistrationState::Confirmed);

        $this->actingAs($user)
            ->getJson('/mis-inscripciones')
            ->assertForbidden()
            ->assertHeader('X-Email-Verification-Required', '1')
            ->assertJsonPath('error', 'email_verification_required')
            ->assertJsonMissingPath('data');
    }

    public function requireVerification(): void
    {
        app(SettingsServiceInterface::class)->set('auth_email_verification_required', '1');
    }

    private function mustVerifyUser(): UserModel
    {
        $this->requireVerification();

        return UserModel::factory()->unverified()->create();
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
