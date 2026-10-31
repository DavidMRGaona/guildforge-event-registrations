<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Integration\Database;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Modules\EventRegistrations\Infrastructure\Surveys\EndedEventAnnouncer;
use Tests\Support\Database\RunsMigrationFiles;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Installing the version that announces ended events must not announce every event the site
 * ever held: the migration marks the events that had already ended as announced.
 */
final class SurveyEndsBackfillMigrationTest extends ModuleTestCase
{
    use RunsMigrationFiles;

    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    private const string MIGRATION = __DIR__.'/../../../database/migrations/2026_10_31_000001_create_event_registrations_survey_ends_table.php';

    public function test_only_events_already_ended_are_marked_as_announced(): void
    {
        $this->useUtcStorage();
        $this->travelTo(new DateTimeImmutable('2030-01-05T12:00:00+00:00'));
        $this->runMigrationFile(self::MIGRATION, 'down');

        $past = EventModel::factory()->create(['start_date' => new DateTimeImmutable('2030-01-04T08:00:00+00:00'), 'end_date' => new DateTimeImmutable('2030-01-04T11:00:00+00:00')]);
        $endingNow = EventModel::factory()->create(['start_date' => new DateTimeImmutable('2030-01-05T08:00:00+00:00'), 'end_date' => new DateTimeImmutable('2030-01-05T12:00:00+00:00')]);
        $future = EventModel::factory()->create(['start_date' => new DateTimeImmutable('2030-01-05T08:00:00+00:00'), 'end_date' => new DateTimeImmutable('2030-01-05T12:00:01+00:00')]);

        $this->runMigrationFile(self::MIGRATION);

        $marked = DB::table(EndedEventAnnouncer::TABLE)->pluck('event_id')->map(static fn (mixed $id): string => (string) $id)->sort()->values()->all();
        $expected = [$past->id, $endingNow->id];
        sort($expected);

        $this->assertSame($expected, $marked);
        $this->assertNotContains($future->id, $marked);
    }
}
