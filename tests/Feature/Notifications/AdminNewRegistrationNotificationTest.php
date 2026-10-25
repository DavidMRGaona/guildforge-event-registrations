<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Feature\Notifications;

use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\EventRegistrations\Domain\Entities\EventRegistration;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Domain\ValueObjects\EventRegistrationId;
use Modules\EventRegistrations\Filament\RelationManagers\RegistrationConfigRelationManager;
use Modules\EventRegistrations\Filament\RelationManagers\RegistrationsRelationManager;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationConfigModel;
use Modules\EventRegistrations\Notifications\AdminNewRegistrationNotification;
use Tests\Support\Modules\ModuleTestCase;

final class AdminNewRegistrationNotificationTest extends ModuleTestCase
{
    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    public function test_the_link_opens_the_registrations_tab_of_the_event(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $event = EventModel::factory()->create();
        EventRegistrationConfigModel::create(['event_id' => $event->id, 'registration_enabled' => true]);
        $registration = new EventRegistration(
            id: EventRegistrationId::generate(),
            eventId: $event->id,
            userId: $admin->id,
            state: RegistrationState::Pending,
        );

        $url = (new AdminNewRegistrationNotification($event, $registration, $admin))->toMail($admin)->actionUrl;
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        // The panel finds events by slug: the link must carry it, not the id
        if (preg_match('~/admin/events/([^/]+)/edit$~', (string) parse_url($url, PHP_URL_PATH), $path) !== 1) {
            $this->fail("The link does not open an event: {$url}");
        }
        $this->assertSame($event->slug, $path[1]);

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::withQueryParams($query)
            ->test(EditEvent::class, ['record' => $path[1]])
            ->assertSeeLivewire(RegistrationsRelationManager::class) // @phpstan-ignore method.notFound (resolved at runtime by Testable::__call)
            ->assertDontSeeLivewire(RegistrationConfigRelationManager::class);
    }
}
