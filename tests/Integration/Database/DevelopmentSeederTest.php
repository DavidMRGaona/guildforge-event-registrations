<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Integration\Database;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Modules\EventRegistrations\Database\Seeders\DevelopmentSeeder;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationConfigModel;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationModel;
use Tests\Support\Modules\ModuleTestCase;

final class DevelopmentSeederTest extends ModuleTestCase
{
    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    private function runSeeder(): void
    {
        require_once dirname(__DIR__, 3).'/database/seeders/DevelopmentSeeder.php';

        (new DevelopmentSeeder)->setContainer($this->app)->run();
    }

    public function test_it_writes_nothing_in_production(): void
    {
        // Enabling the module in testing already ran the seeder: empty what it wrote first
        EventRegistrationModel::query()->delete();
        EventRegistrationConfigModel::query()->delete();
        EventModel::query()->delete();
        UserModel::where('email', 'like', '%@example.local')->forceDelete();

        $this->app['env'] = 'production';
        $this->assertTrue($this->app->environment('production'));

        $this->runSeeder();

        $this->assertSame(0, EventRegistrationModel::count());
        $this->assertSame(0, EventRegistrationConfigModel::count());
        $this->assertSame(0, EventModel::count());
        $this->assertSame(0, UserModel::where('email', 'like', '%@example.local')->count());
    }

    public function test_it_is_idempotent(): void
    {
        $this->app['env'] = 'local';
        $this->runSeeder();
        $counts = $this->counts();

        $this->runSeeder();

        $this->assertSame($counts, $this->counts());
        $this->assertGreaterThan(0, $counts[0]);
    }

    public function test_it_seeds_every_registration_state_and_a_members_only_event_without_notifying(): void
    {
        $this->app['env'] = 'local';
        Mail::fake();
        Notification::fake();

        $this->runSeeder();

        foreach ([RegistrationState::Pending, RegistrationState::Confirmed, RegistrationState::WaitingList, RegistrationState::Cancelled, RegistrationState::Rejected] as $state) {
            $this->assertGreaterThan(0, EventRegistrationModel::where('state', $state->value)->count(), $state->value);
        }

        $cancelled = EventRegistrationModel::where('state', RegistrationState::Cancelled->value)->first();
        $waiting = EventRegistrationModel::where('state', RegistrationState::WaitingList->value)->first();
        $this->assertNotNull($cancelled);
        $this->assertNotNull($waiting);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertSame(1, $waiting->position);
        $this->assertSame(1, EventRegistrationConfigModel::where('members_only', true)->count());
        $this->assertTrue(EventRegistrationConfigModel::with('event')->get()->every(fn (EventRegistrationConfigModel $c): bool => $c->event->start_date->isFuture()));

        Mail::assertNothingSent();
        Notification::assertNothingSent();
    }

    /**
     * @return array{int, int, int}
     */
    private function counts(): array
    {
        return [EventRegistrationModel::count(), EventRegistrationConfigModel::count(), EventModel::count()];
    }
}
