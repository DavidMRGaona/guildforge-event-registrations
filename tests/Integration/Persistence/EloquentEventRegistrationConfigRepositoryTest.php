<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Integration\Persistence;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Str;
use Modules\EventRegistrations\Domain\Repositories\EventRegistrationConfigRepositoryInterface;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationConfigModel;
use Tests\Support\Modules\ModuleTestCase;

final class EloquentEventRegistrationConfigRepositoryTest extends ModuleTestCase
{
    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    private EventRegistrationConfigRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->app->make(EventRegistrationConfigRepositoryInterface::class);
    }

    public function test_a_saved_config_carries_the_start_of_its_event(): void
    {
        $event = $this->eventStartingAt('2030-04-12 10:00:00');
        EventRegistrationConfigModel::create(['event_id' => $event->id, 'registration_enabled' => true]);

        $config = $this->repository->findByEvent($event->id);

        $this->assertNotNull($config);
        $this->assertSame('2030-04-12 10:00:00', $config->eventStartsAt()?->format('Y-m-d H:i:s'));
    }

    public function test_the_default_config_carries_the_start_of_its_event(): void
    {
        $event = $this->eventStartingAt('2030-04-12 10:00:00');

        $config = $this->repository->findByEventOrDefault($event->id);

        $this->assertSame('2030-04-12 10:00:00', $config->eventStartsAt()?->format('Y-m-d H:i:s'));
    }

    public function test_the_config_of_an_unknown_event_has_no_start_and_counts_as_started(): void
    {
        $config = $this->repository->findByEventOrDefault((string) Str::uuid());

        $this->assertNull($config->eventStartsAt());
        $this->assertTrue($config->hasEventStarted());
    }

    public function test_the_event_starts_at_the_wall_clock_time_of_the_site_timezone(): void
    {
        $siteTimezone = new DateTimeZone((string) config('app.timezone'));
        $event = $this->eventStartingAt('2030-04-12 10:00:00');

        $config = $this->repository->findByEventOrDefault($event->id);

        $this->assertFalse($config->hasEventStarted(new DateTimeImmutable('2030-04-12 09:59:59', $siteTimezone)));
        $this->assertTrue($config->hasEventStarted(new DateTimeImmutable('2030-04-12 10:00:00', $siteTimezone)));
    }

    private function eventStartingAt(string $startDate): EventModel
    {
        return EventModel::factory()->create([
            'start_date' => $startDate,
            'end_date' => '2030-04-12 14:00:00',
        ]);
    }
}
