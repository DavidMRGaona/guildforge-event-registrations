<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Feature\Time;

use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Infrastructure\Persistence\Eloquent\Concerns\StoresInstantsInStorageTimezone;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\EventRegistrations\Application\DTOs\EventRegistrationResponseDTO;
use Modules\EventRegistrations\Application\DTOs\RegistrationListItemDTO;
use Modules\EventRegistrations\Application\DTOs\UpdateRegistrationConfigDTO;
use Modules\EventRegistrations\Application\Services\RegistrationQueryServiceInterface;
use Modules\EventRegistrations\Domain\Entities\EventRegistration;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Domain\ValueObjects\EventRegistrationId;
use Modules\EventRegistrations\Filament\Pages\EventRegistrationConfigPage;
use Modules\EventRegistrations\Filament\RelationManagers\RegistrationConfigRelationManager;
use Modules\EventRegistrations\Filament\RelationManagers\RegistrationsRelationManager;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationConfigModel;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationModel;
use Modules\EventRegistrations\Notifications\EventReminderNotification;
use Modules\EventRegistrations\Notifications\RegistrationConfirmedNotification;
use Modules\EventRegistrations\Notifications\UserRegisteredNotification;
use Modules\EventRegistrations\Notifications\WaitingListPromotedNotification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;
use Tests\Support\Time\UsesUtcStorage;

/**
 * With UTC storage the instants in the database are UTC, but whoever reads a mail, a CSV or the
 * panel sees the site's Madrid wall time.
 */
final class UtcStorageTest extends ModuleTestCase
{
    use InteractsWithModulePanel;
    use UsesUtcStorage;

    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    /** 12:00 in Madrid (CEST). */
    private const string EVENT_START = '2026-07-01T10:00:00Z';

    private const string MADRID_ISO = '2026-07-01T12:00:00+02:00';

    protected function setUp(): void
    {
        parent::setUp();

        $this->useUtcStorage();
        app()->setLocale('es');
    }

    public function test_every_model_stores_instants_in_the_storage_timezone(): void
    {
        $files = glob(dirname(__DIR__, 3).'/src/Infrastructure/Persistence/Eloquent/Models/*.php') ?: [];
        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $class = 'Modules\\EventRegistrations\\Infrastructure\\Persistence\\Eloquent\\Models\\'.basename($file, '.php');

            $this->assertContains(StoresInstantsInStorageTimezone::class, class_uses_recursive($class), $class);
        }
    }

    public function test_the_serialized_instants_carry_the_display_offset(): void
    {
        $instant = new DateTimeImmutable(self::EVENT_START);
        $event = $this->event();
        $user = UserModel::factory()->create();
        $entity = new EventRegistration(
            id: EventRegistrationId::generate(),
            eventId: $event->id,
            userId: $user->id,
            state: RegistrationState::Confirmed,
            confirmedAt: $instant,
            createdAt: $instant,
        );

        $this->assertSame(self::MADRID_ISO, EventRegistrationResponseDTO::fromEntity($entity)->confirmedAt);
        $this->assertSame(self::MADRID_ISO, RegistrationListItemDTO::fromEntity($entity)->createdAt);

        $dto = new UpdateRegistrationConfigDTO(eventId: $event->id, registrationOpensAt: $instant);
        $this->assertSame(self::MADRID_ISO, $dto->toArray()['registration_opens_at']);

        EventRegistrationConfigModel::create([
            'event_id' => $event->id,
            'registration_enabled' => true,
            'cancellation_deadline' => CarbonImmutable::parse(self::EVENT_START),
        ]);
        $query = app(RegistrationQueryServiceInterface::class);
        $this->assertSame(self::MADRID_ISO, $query->getEventStatus($event->id)->toArray()['cancellation_deadline']);

        EventRegistrationModel::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'state' => RegistrationState::Confirmed,
        ]);
        $registered = $query->getUserRegistrationsWithEvents($user->id)[0]->event;
        $this->assertNotNull($registered);
        $this->assertSame(self::MADRID_ISO, $registered->startDate);
    }

    /**
     * @return array<string, array{0: class-string}>
     */
    public static function startDateNotifications(): array
    {
        return [
            'registered' => [UserRegisteredNotification::class],
            'confirmed' => [RegistrationConfirmedNotification::class],
            'promoted' => [WaitingListPromotedNotification::class],
            'reminder' => [EventReminderNotification::class],
        ];
    }

    /**
     * @param  class-string  $notification
     */
    #[DataProvider('startDateNotifications')]
    public function test_the_mails_show_the_event_start_in_madrid_time(string $notification): void
    {
        $event = $this->event();
        $registration = new EventRegistration(
            id: EventRegistrationId::generate(),
            eventId: $event->id,
            userId: 'user-1',
            state: RegistrationState::Confirmed,
        );
        $user = UserModel::factory()->make(['name' => 'John']);
        $mail = match ($notification) {
            EventReminderNotification::class => (new EventReminderNotification($event))->toMail($user),
            RegistrationConfirmedNotification::class => (new RegistrationConfirmedNotification($event, $registration))->toMail($user),
            WaitingListPromotedNotification::class => (new WaitingListPromotedNotification($event, $registration))->toMail($user),
            default => (new UserRegisteredNotification($event, $registration))->toMail($user),
        };

        $text = implode("\n", [$mail->subject, ...$mail->introLines]);

        $this->assertStringContainsString('01/07/2026 12:00', $text);
        $this->assertStringNotContainsString('10:00', $text);
    }

    public function test_the_export_shows_madrid_time(): void
    {
        $this->actingAsPanelAdmin();
        $event = $this->event();
        EventRegistrationConfigModel::create(['event_id' => $event->id, 'registration_enabled' => true]);
        $registration = new EventRegistrationModel([
            'event_id' => $event->id,
            'user_id' => UserModel::factory()->create()->id,
            'state' => RegistrationState::Confirmed,
            'confirmed_at' => CarbonImmutable::parse(self::EVENT_START),
        ]);
        $registration->created_at = Carbon::parse(self::EVENT_START);
        $registration->save();

        $component = Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $event, 'pageClass' => EditEvent::class])
            ->callTableAction('export');
        $content = data_get($component->__get('effects'), 'download.content');
        $csv = base64_decode(is_string($content) ? $content : '');

        $this->assertSame(2, substr_count($csv, '01/07/2026 12:00'));
    }

    public function test_the_config_tab_shows_madrid_wall_time_and_saves_the_utc_instant(): void
    {
        $this->actingAsPanelAdmin();
        $event = $this->event();
        EventRegistrationConfigModel::create([
            'event_id' => $event->id,
            'registration_enabled' => true,
            'registration_opens_at' => CarbonImmutable::parse('2026-06-01T08:00:00Z'),
        ]);

        Livewire::test(RegistrationConfigRelationManager::class, ['ownerRecord' => $event, 'pageClass' => EditEvent::class])
            ->assertSet('configData.registration_opens_at', '2026-06-01 10:00:00')
            ->set('configData.registration_opens_at', '2026-06-02 12:00:00')
            ->set('configData.registration_closes_at', '2026-07-01 12:00:00')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('2026-06-02 10:00:00', $this->storedValue('registration_opens_at'));
        $this->assertSame('2026-07-01 10:00:00', $this->storedValue('registration_closes_at'));
    }

    public function test_the_config_page_shows_madrid_wall_time_and_saves_the_utc_instant(): void
    {
        $this->actingAsPanelAdmin();
        $event = $this->event();
        EventRegistrationConfigModel::create([
            'event_id' => $event->id,
            'registration_enabled' => true,
            'cancellation_deadline' => CarbonImmutable::parse('2026-06-30T08:00:00Z'),
        ]);

        Livewire::test(EventRegistrationConfigPage::class, ['eventId' => $event->id])
            ->assertSet('data.cancellation_deadline', '2026-06-30 10:00:00')
            ->set('data.registration_opens_at', '2026-06-02 12:00:00')
            ->set('data.cancellation_deadline', '2026-07-01 12:00:00')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('2026-06-02 10:00:00', $this->storedValue('registration_opens_at'));
        $this->assertSame('2026-07-01 10:00:00', $this->storedValue('cancellation_deadline'));
    }

    public function test_the_config_forms_cap_dates_at_the_event_start_in_madrid_time(): void
    {
        $this->actingAsPanelAdmin();
        $event = $this->event();

        Livewire::test(RegistrationConfigRelationManager::class, ['ownerRecord' => $event, 'pageClass' => EditEvent::class])
            ->set('configData.registration_enabled', true)
            ->set('configData.registration_opens_at', '2026-06-01 10:00:00')
            ->set('configData.registration_closes_at', '2026-07-01 12:01:00')
            ->call('save')
            ->assertHasErrors(['configData.registration_closes_at' => 'before_or_equal'])
            ->assertSee('No puede ser posterior al inicio del evento (01/07/2026 12:00)');

        Livewire::test(EventRegistrationConfigPage::class, ['eventId' => $event->id])
            ->set('data.registration_opens_at', '2026-06-01 10:00:00')
            ->set('data.cancellation_deadline', '2026-07-01 12:00:00')
            ->call('save')
            ->assertHasNoErrors();
    }

    private function event(): EventModel
    {
        return EventModel::factory()->published()->create([
            'start_date' => CarbonImmutable::parse(self::EVENT_START),
            'end_date' => CarbonImmutable::parse('2026-07-01T14:00:00Z'),
        ]);
    }

    private function storedValue(string $column): ?string
    {
        $value = DB::table('event_registrations_configs')->value($column);

        return is_string($value) ? substr($value, 0, 19) : null;
    }
}
