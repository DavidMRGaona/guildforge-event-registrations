<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Feature\Notifications;

use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\EventRegistrations\Filament\RelationManagers\RegistrationConfigRelationManager;
use Modules\EventRegistrations\Notifications\AdminNewRegistrationNotification;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The notification email typed in the config tab reaches the organisers: once saved, a new
 * registration sends them the notice.
 */
final class AdminNotificationEndToEndTest extends ModuleTestCase
{
    use CreatesUsersWithPermissions;
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    public function test_a_registration_notifies_the_email_saved_in_the_config_tab(): void
    {
        Notification::fake();
        $this->registerOnAdminPanel();
        $this->actingAsPanelAdmin();
        $event = EventModel::factory()->published()->create([
            'start_date' => now()->addWeek(),
            'end_date' => now()->addWeek()->addHours(4),
        ]);

        Livewire::test(RegistrationConfigRelationManager::class, ['ownerRecord' => $event, 'pageClass' => EditEvent::class])
            ->set('configData.registration_enabled', true)
            ->set('configData.notification_email', 'organisers@example.test')
            ->call('save')
            ->assertHasNoErrors();

        $this->actingAs(UserModel::factory()->create())
            ->postJson("/eventos/{$event->id}/inscripcion")
            ->assertCreated();

        Notification::assertSentOnDemand(
            AdminNewRegistrationNotification::class,
            fn (AdminNewRegistrationNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'organisers@example.test',
        );
    }
}
