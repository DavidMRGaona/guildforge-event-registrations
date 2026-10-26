<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Feature\Http;

use Tests\Support\Modules\ModuleTestCase;

/**
 * The module has no component tests, so this checks in the source that the registration button
 * offers cancelling from the server's `can_cancel` and not from the registration window, and
 * that, once the user can no longer cancel, it stays disabled and says why (as game tables do).
 */
final class CancellationWindowFrontendTest extends ModuleTestCase
{
    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    private const string MODULE_ROOT = __DIR__.'/../../..';

    public function test_the_registration_button_cancels_within_the_server_cancellation_window(): void
    {
        $this->assertMatchesRegularExpression(
            '/const canCancel = computed\([^;]*hasActiveRegistration\.value && config\.value\?\.can_cancel === true/s',
            $this->componentSource(),
        );
    }

    public function test_an_active_registration_is_not_disabled_by_the_registration_window(): void
    {
        if (preg_match('/const isUnavailable = computed\(\(\) => \{(.*?)\n\}\);/s', $this->componentSource(), $body) !== 1) {
            $this->fail('isUnavailable is not in the component');
        }

        $byCancellation = strpos($body[1], 'if (hasActiveRegistration.value) return !canCancel.value;');
        $byRegistration = strpos($body[1], 'if (!config.value.is_open) return true;');

        $this->assertNotFalse($byCancellation);
        $this->assertNotFalse($byRegistration);
        $this->assertLessThan($byRegistration, $byCancellation);
    }

    public function test_the_button_stays_rendered_once_an_active_registration_can_no_longer_be_cancelled(): void
    {
        $source = $this->componentSource();

        $this->assertMatchesRegularExpression('/<button\s+v-if="!showForm"\s/', $source);
        $this->assertStringNotContainsString('isCancellationClosed', $source);
    }

    public function test_a_closed_cancellation_says_why_from_the_server_reason(): void
    {
        $source = $this->componentSource();

        $this->assertMatchesRegularExpression(
            '/if \(hasActiveRegistration\.value\) \{\s*if \(canCancel\.value\) return t\(\'eventRegistrations\.cancelRegistration\'\);\s*return cancellationClosedText\.value;\s*\}/',
            $source,
        );
        $this->assertMatchesRegularExpression(
            "/const CANCELLATION_CLOSED_KEYS: Record<CancellationClosedReason, string> = \\{\\s*event_ended: 'eventRegistrationsCancellation\\.closed\\.eventEnded',\\s*event_started: 'eventRegistrationsCancellation\\.closed\\.eventStarted',\\s*deadline_passed: 'eventRegistrationsCancellation\\.closed\\.deadlinePassed',\\s*\\};/",
            $source,
        );

        foreach (['es', 'en'] as $locale) {
            $translations = file_get_contents(self::MODULE_ROOT."/resources/js/locales/{$locale}.ts");
            $this->assertIsString($translations);
            $this->assertMatchesRegularExpression(
                '/eventRegistrationsCancellation: \{\s*closed: \{\s*eventEnded:[^}]*eventStarted:[^}]*deadlinePassed:/s',
                $translations,
            );
        }
    }

    public function test_an_error_answer_never_replaces_the_config_or_the_registration_with_undefined(): void
    {
        $source = $this->componentSource();

        $this->assertStringContainsString('const data = response.ok ? await response.json() : null;', $source);
        $this->assertStringContainsString('config.value = data?.data ?? null;', $source);
        $this->assertStringContainsString('registration.value = data?.data ?? null;', $source);
        $this->assertStringNotContainsString('config.value = data.data;', $source);
    }

    public function test_a_refused_cancellation_reloads_the_status_so_the_button_can_say_why(): void
    {
        $this->assertMatchesRegularExpression(
            '/if \(!response\.ok\) \{\s*error\.value = data\.message;[^}]*await fetchStatus\(\);\s*return;\s*\}/s',
            $this->componentSource(),
        );
    }

    private function componentSource(): string
    {
        $source = file_get_contents(self::MODULE_ROOT.'/resources/js/components/RegistrationButton.vue');
        $this->assertIsString($source);

        return $source;
    }
}
