<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Feature\Http;

use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Domain\Enums\UserRole;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Notification;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationConfigModel;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationModel;
use Tests\Support\Modules\ModuleTestCase;

final class MembersOnlyRegistrationTest extends ModuleTestCase
{
    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    private EventModel $event;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->event = EventModel::factory()->create();
        EventRegistrationConfigModel::create([
            'event_id' => $this->event->id,
            'registration_enabled' => true,
            'members_only' => true,
        ]);
    }

    public function test_a_user_without_roles_is_refused_with_a_forbidden_response(): void
    {
        $user = UserModel::factory()->create();
        $user->roles()->detach();
        $this->actingAs($user);

        $this->postJson("/eventos/{$this->event->id}/inscripcion")
            ->assertForbidden()
            ->assertJsonPath('message', __('event-registrations::messages.errors.members_only'));

        $this->assertSame(0, $this->registrationCount());
    }

    public function test_a_user_with_only_the_member_role_is_refused(): void
    {
        $this->actingAs($this->userWithRole(UserRole::Member->value));

        $this->postJson("/eventos/{$this->event->id}/inscripcion")
            ->assertForbidden()
            ->assertJsonPath('message', __('event-registrations::messages.errors.members_only'));

        $this->assertSame(0, $this->registrationCount());
    }

    public function test_an_association_member_registers(): void
    {
        $this->actingAs($this->userWithRole(AssociationMembershipServiceInterface::DEFAULT_ROLE));

        $this->postJson("/eventos/{$this->event->id}/inscripcion")->assertCreated();

        $this->assertSame(1, $this->registrationCount());
    }

    public function test_the_configured_association_member_role_is_respected(): void
    {
        app(SettingsServiceInterface::class)->set(AssociationMembershipServiceInterface::SETTING_KEY, 'junta');

        $this->actingAs($this->userWithRole(AssociationMembershipServiceInterface::DEFAULT_ROLE));
        $this->postJson("/eventos/{$this->event->id}/inscripcion")->assertForbidden();

        $this->actingAs($this->userWithRole('junta'));
        $this->postJson("/eventos/{$this->event->id}/inscripcion")->assertCreated();

        $this->assertSame(1, $this->registrationCount());
    }

    public function test_an_event_open_to_everyone_accepts_a_user_with_only_the_member_role(): void
    {
        $event = EventModel::factory()->create();
        EventRegistrationConfigModel::create([
            'event_id' => $event->id,
            'registration_enabled' => true,
            'members_only' => false,
        ]);
        $this->actingAs($this->userWithRole(UserRole::Member->value));

        $this->postJson("/eventos/{$event->id}/inscripcion")->assertCreated();

        $this->getJson("/eventos/{$event->id}/inscripcion/estado")
            ->assertOk()
            ->assertJsonPath('data.members_only', false)
            ->assertJsonPath('data.meets_members_only', true);
    }

    public function test_the_status_tells_a_user_with_only_the_member_role_that_they_cannot_register(): void
    {
        $this->actingAs($this->userWithRole(UserRole::Member->value));

        $this->getJson("/eventos/{$this->event->id}/inscripcion/estado")
            ->assertOk()
            ->assertJsonPath('data.members_only', true)
            ->assertJsonPath('data.meets_members_only', false);
    }

    public function test_the_status_tells_an_association_member_that_they_can_register(): void
    {
        $this->actingAs($this->userWithRole(AssociationMembershipServiceInterface::DEFAULT_ROLE));

        $this->getJson("/eventos/{$this->event->id}/inscripcion/estado")
            ->assertOk()
            ->assertJsonPath('data.meets_members_only', true);
    }

    public function test_the_status_follows_the_configured_association_member_role(): void
    {
        app(SettingsServiceInterface::class)->set(AssociationMembershipServiceInterface::SETTING_KEY, 'junta');

        $this->actingAs($this->userWithRole(AssociationMembershipServiceInterface::DEFAULT_ROLE));
        $this->getJson("/eventos/{$this->event->id}/inscripcion/estado")
            ->assertJsonPath('data.meets_members_only', false);

        $this->actingAs($this->userWithRole('junta'));
        $this->getJson("/eventos/{$this->event->id}/inscripcion/estado")
            ->assertJsonPath('data.meets_members_only', true);
    }

    public function test_the_status_tells_a_guest_that_they_do_not_meet_the_members_only_requirement(): void
    {
        $this->getJson("/eventos/{$this->event->id}/inscripcion/estado")
            ->assertOk()
            ->assertJsonPath('data.members_only', true)
            ->assertJsonPath('data.meets_members_only', false);
    }

    private function userWithRole(string $roleName): UserModel
    {
        $role = RoleModel::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName)]);
        $user = UserModel::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    private function registrationCount(): int
    {
        return EventRegistrationModel::query()->where('event_id', $this->event->id)->count();
    }
}
