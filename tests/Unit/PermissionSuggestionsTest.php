<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Unit;

use App\Application\Modules\DTOs\PermissionDTO;
use Modules\EventRegistrations\EventRegistrationsServiceProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class PermissionSuggestionsTest extends TestCase
{
    /**
     * Since core 3.26.0 editor is the content role: the module's permissions go to the roles
     * each association creates, so none is marked as suggested for editor.
     */
    public function test_no_permission_is_suggested_to_the_content_role(): void
    {
        $suggested = array_filter(
            (new EventRegistrationsServiceProvider($this->app))->registerPermissions(),
            fn (PermissionDTO $permission): bool => in_array('editor', $permission->roles, true),
        );

        $this->assertSame([], array_values(array_map(fn (PermissionDTO $permission): string => $permission->name, $suggested)));
    }

    /**
     * Seeing what attendees think of an event is not editing it: event surveys have their own
     * permission, handed to a role by the association, never to anyone by default.
     */
    public function test_event_surveys_have_a_permission_of_their_own_without_a_suggested_role(): void
    {
        $permissions = (new EventRegistrationsServiceProvider($this->app))->registerPermissions();
        $surveys = array_values(array_filter($permissions, fn (PermissionDTO $permission): bool => $permission->name === 'registrations.manage_surveys'));

        $this->assertCount(1, $surveys);
        $this->assertSame([], $surveys[0]->roles);
    }

    /**
     * The role editor shows each label as it comes: a missing translation would show its raw key.
     *
     * @return array<string, array{string}>
     */
    public static function locales(): array
    {
        return ['spanish' => ['es'], 'english' => ['en']];
    }

    #[DataProvider('locales')]
    public function test_every_permission_label_is_translated(string $locale): void
    {
        $this->app['translator']->addNamespace('event-registrations', dirname(__DIR__, 2).'/lang');
        app()->setLocale($locale);

        foreach ((new EventRegistrationsServiceProvider($this->app))->registerPermissions() as $permission) {
            $this->assertStringNotContainsString('event-registrations::', $permission->label, $permission->name);
        }
    }
}
