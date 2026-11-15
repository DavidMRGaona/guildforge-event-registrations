<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Feature\Filament;

use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Contracts\Auth\Access\Gate;
use Modules\EventRegistrations\Filament\RelationManagers\RegistrationsRelationManager;
use Modules\EventRegistrations\Policies\RegistrationListAccess;
use Modules\EventRegistrations\Tests\Support\BuildsProfileRegistrations;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The public profile and the panel's «Inscripciones» tab answer through one class: whoever the profile
 * lets see the people registered in an event is exactly who gets to that tab, and nobody else.
 */
final class RegistrationListAccessTest extends ModuleTestCase
{
    use BuildsProfileRegistrations;
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    private EventModel $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pinTheClock();
        $this->registerOnAdminPanel();
        $this->event = $this->upcomingEvent();
        $this->register($this->event);
    }

    public function test_the_rule_matches_what_the_panel_really_lets_each_person_open(): void
    {
        $access = $this->app->make(RegistrationListAccess::class);
        $opens = [];

        foreach ($this->viewers() as $name => $viewer) {
            if (! $viewer instanceof UserModel || $name === 'owner') {
                continue;
            }

            $this->actingAs($viewer);
            $pageOpens = $this->get("/admin/events/{$this->event->id}/edit")->getStatusCode() === 200;
            $tabShows = RegistrationsRelationManager::canViewForRecord($this->event, EditEvent::class);
            $opens[$name] = $pageOpens && $tabShows;

            $this->assertSame($opens[$name], $access->mayOpenEventTabs($viewer), "The profile and the panel disagree for: {$name}");
        }

        // The matrix is not trivially all-true or all-false
        $this->assertContains(true, $opens);
        $this->assertContains(false, $opens);
        $this->assertTrue($opens['staff who open the tab']);
        $this->assertFalse($opens['staff with only registrations.view']);
        $this->assertFalse($opens['the tab permissions without the panel']);
    }

    public function test_a_guest_opens_nothing(): void
    {
        $access = $this->app->make(RegistrationListAccess::class);

        $this->assertFalse($access->mayOpenEventTabs(null));
        $this->assertFalse($access->mayListRegistrations(null));
    }

    public function test_the_event_policies_do_not_read_the_event_so_one_answer_covers_all_events(): void
    {
        // The profile's count asks for an event with no record: this is what makes that sound
        $other = $this->upcomingEvent('Otro evento', 20);

        foreach ($this->viewers() as $viewer) {
            if (! $viewer instanceof UserModel) {
                continue;
            }
            $gate = $this->app->make(Gate::class)->forUser($viewer);

            foreach (['update', 'delete'] as $ability) {
                $this->assertSame($gate->allows($ability, $this->event), $gate->allows($ability, $other));
                $this->assertSame($gate->allows($ability, $this->event), $gate->allows($ability, new EventModel));
            }
        }
    }
}
