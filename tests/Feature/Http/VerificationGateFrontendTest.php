<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Feature\Http;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The module has no component tests, so this checks that the components that send to routes
 * behind `auth` use the core's useVerificationGate() (core 3.10): the registration button
 * disables register and cancel with their own reason and goes to the notice on an unexpected
 * verification 403, and the profile tab shows why it cannot list the registrations.
 */
final class VerificationGateFrontendTest extends ModuleTestCase
{
    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    private const string MODULE_ROOT = __DIR__.'/../../..';

    public function test_the_module_requires_the_core_that_shares_the_verification_state(): void
    {
        $manifest = json_decode((string) file_get_contents(self::MODULE_ROOT.'/module.json'), true);

        $this->assertIsArray($manifest);
        // At least 3.10, which shares the verification state; later bumps keep the guarantee
        $this->assertMatchesRegularExpression('/^\^3\.(\d+)$/', (string) ($manifest['requires']['core'] ?? ''));
        $this->assertTrue(version_compare(substr((string) $manifest['requires']['core'], 1), '3.10', '>='));
    }

    public function test_the_registration_button_is_gated_for_register_and_cancel(): void
    {
        $source = $this->componentSource('RegistrationButton.vue');

        $this->assertStringContainsString("from '@/composables/useVerificationGate'", $source);
        $this->assertStringContainsString("reason('register')", $source);
        $this->assertStringContainsString("reason('cancelRegistration')", $source);
        $this->assertStringContainsString('isVerificationRequired(', $source);
        $this->assertStringContainsString('goToNotice()', $source);
        $this->assertStringContainsString('useId()', $source);
        $this->assertStringContainsString(':href="gate.noticeUrl"', $source);
    }

    public function test_the_profile_tab_shows_why_it_cannot_list_the_registrations(): void
    {
        $source = $this->componentSource('UserRegistrations.vue');

        $this->assertStringContainsString("from '@/composables/useVerificationGate'", $source);
        $this->assertStringContainsString("'verification-required'", $source);
        $this->assertStringContainsString('isVerificationRequired(', $source);
        $this->assertStringContainsString("t('eventRegistrationsProfile.verificationRequired')", $source);
        $this->assertStringContainsString(':href="gate.noticeUrl"', $source);
    }

    public function test_a_blocked_profile_tab_starts_in_the_verification_state_without_a_spinner(): void
    {
        $source = $this->componentSource('UserRegistrations.vue');

        $this->assertMatchesRegularExpression(
            "/const loadState = ref<LoadState>\\(\\s*gate\\.blocked\\.value \\? 'verification-required' : 'loading',?\\s*\\)/",
            $source,
        );
    }

    public function test_the_profile_tab_announces_the_verification_reached_by_a_request(): void
    {
        $source = $this->componentSource('UserRegistrations.vue');

        $this->assertSame(1, preg_match('/<p role="status" class="sr-only">(.*?)<\/p>/s', $source, $liveRegion));
        $this->assertStringContainsString("t('eventRegistrationsProfile.verificationRequired')", $liveRegion[1] ?? '');
    }

    public function test_the_profile_tab_explains_the_verification_in_spanish_and_english(): void
    {
        $translations = [];

        $this->actingAs(UserModel::factory()->create())
            ->get('/perfil')
            ->assertInertia(function (Assert $page) use (&$translations): void {
                $translations = $page->toArray()['props']['moduleTranslations']['event-registrations'] ?? [];
            });

        $this->assertSame(
            'Para ver tus inscripciones, verifica tu email.',
            $translations['es']['eventRegistrationsProfile']['verificationRequired'] ?? null,
        );
        $this->assertSame(
            'To see your registrations, verify your email.',
            $translations['en']['eventRegistrationsProfile']['verificationRequired'] ?? null,
        );
    }

    private function componentSource(string $name): string
    {
        $source = file_get_contents(self::MODULE_ROOT.'/resources/js/components/'.$name);
        $this->assertIsString($source);

        return $source;
    }
}
