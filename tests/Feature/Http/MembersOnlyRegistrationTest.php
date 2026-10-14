<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Feature\Http;

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

    public function test_a_non_member_is_refused_with_a_forbidden_response(): void
    {
        $this->actingAs(UserModel::factory()->create());

        $this->postJson("/eventos/{$this->event->id}/inscripcion")
            ->assertForbidden()
            ->assertJsonPath('message', __('event-registrations::messages.errors.members_only'));

        $this->assertSame(0, EventRegistrationModel::query()->where('event_id', $this->event->id)->count());
    }

    public function test_a_member_registers(): void
    {
        $member = UserModel::factory()->create();
        $role = RoleModel::firstOrCreate(['name' => 'member'], ['display_name' => 'Member']);
        $member->roles()->attach($role->id);
        $this->actingAs($member);

        $this->postJson("/eventos/{$this->event->id}/inscripcion")->assertCreated();

        $this->assertSame(1, EventRegistrationModel::query()->where('event_id', $this->event->id)->count());
    }
}
