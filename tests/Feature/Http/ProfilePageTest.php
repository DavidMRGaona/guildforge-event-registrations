<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Feature\Http;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Modules\ModuleTestCase;

/**
 * What the profile page receives from this module: the slots it fills and the texts the
 * frontend merges into vue-i18n. The event page button keeps its slot untouched.
 */
final class ProfilePageTest extends ModuleTestCase
{
    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_profile_page_renders_for_the_signed_in_user(): void
    {
        $user = UserModel::factory()->create();

        $this->actingAs($user)
            ->get('/perfil')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Profile/Show')
                ->where('user.id', $user->id)
            );
    }

    public function test_the_registration_button_keeps_its_slot_on_the_event_page(): void
    {
        $slots = $this->moduleSlots();

        $this->assertSame([[
            'slot' => 'event-detail-actions',
            'component' => 'components/RegistrationButton.vue',
            'module' => 'event-registrations',
            'order' => 0,
            'props' => [],
            'dataKeys' => ['event'],
        ]], $this->registrationsOf('event-registrations', $slots['event-detail-actions'] ?? []));
    }

    public function test_the_eligibility_texts_keep_their_top_level_key(): void
    {
        $translations = $this->moduleTranslations();

        $this->assertSame('Solo para socios', $translations['es']['eventRegistrationsEligibility']['membersOnly'] ?? null);
        $this->assertSame('Members only', $translations['en']['eventRegistrationsEligibility']['membersOnly'] ?? null);
    }

    public function test_the_module_texts_never_replace_the_core_event_registrations_block(): void
    {
        // The frontend merges module texts over the core ones key by key at the top level,
        // so a module `eventRegistrations` key would wipe the core texts RegistrationButton uses
        foreach ($this->moduleTranslations() as $locale => $messages) {
            $this->assertArrayNotHasKey('eventRegistrations', $messages, "Locale {$locale}");
        }
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function moduleSlots(): array
    {
        /** @var array<string, list<array<string, mixed>>> $slots */
        $slots = [];

        $this->actingAs(UserModel::factory()->create())
            ->get('/perfil')
            ->assertInertia(function (Assert $page) use (&$slots): void {
                $slots = $page->toArray()['props']['moduleSlots'] ?? [];
            });

        return $slots;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function moduleTranslations(): array
    {
        /** @var array<string, array<string, mixed>> $translations */
        $translations = [];

        $this->actingAs(UserModel::factory()->create())
            ->get('/perfil')
            ->assertInertia(function (Assert $page) use (&$translations): void {
                $translations = $page->toArray()['props']['moduleTranslations']['event-registrations'] ?? [];
            });

        $this->assertArrayHasKey('es', $translations);
        $this->assertArrayHasKey('en', $translations);

        return $translations;
    }

    /**
     * @param  list<array<string, mixed>>  $registrations
     * @return list<array<string, mixed>>
     */
    private function registrationsOf(string $module, array $registrations): array
    {
        return array_values(array_filter(
            $registrations,
            fn (array $registration): bool => ($registration['module'] ?? null) === $module,
        ));
    }
}
