<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Integration\Calendar;

use App\Application\Calendar\DTOs\CalendarEntryReferenceDTO;
use App\Application\Calendar\Services\CalendarAttendanceSourceRegistryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Infrastructure\Calendar\EventAttendanceSource;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationModel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The events a person is confirmed in, as references for their personal calendar.
 */
final class EventAttendanceSourceTest extends ModuleTestCase
{
    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useUtcStorage();
    }

    public function test_the_module_registers_its_source(): void
    {
        $sources = $this->app->make(CalendarAttendanceSourceRegistryInterface::class)->all();

        $this->assertContains(EventAttendanceSource::class, $sources);
    }

    public function test_a_confirmed_registration_points_at_its_event(): void
    {
        $user = UserModel::factory()->create();
        $event = $this->event('2030-03-10 10:00:00', '2030-03-10 14:00:00');
        $this->register($event, $user, RegistrationState::Confirmed);

        $references = $this->ask($user, '2030-03-01', '2030-04-01');

        $this->assertCount(1, $references);
        $this->assertSame('event', $references[0]->sourceType);
        $this->assertSame($event->id, $references[0]->id);
    }

    /**
     * @return array<string, array{RegistrationState}>
     */
    public static function unconfirmedStates(): array
    {
        return [
            'pending' => [RegistrationState::Pending],
            'waiting list' => [RegistrationState::WaitingList],
            'rejected' => [RegistrationState::Rejected],
            'cancelled' => [RegistrationState::Cancelled],
        ];
    }

    #[DataProvider('unconfirmedStates')]
    public function test_registrations_that_are_not_confirmed_are_left_out(RegistrationState $state): void
    {
        $user = UserModel::factory()->create();
        $this->register($this->event('2030-03-10 10:00:00', '2030-03-10 14:00:00'), $user, $state);

        $this->assertSame([], $this->ask($user, '2030-03-01', '2030-04-01'));
    }

    public function test_other_peoples_registrations_are_not_returned(): void
    {
        $me = UserModel::factory()->create();
        $other = UserModel::factory()->create();
        $this->register($this->event('2030-03-10 10:00:00', '2030-03-10 14:00:00'), $other, RegistrationState::Confirmed);

        $this->assertSame([], $this->ask($me, '2030-03-01', '2030-04-01'));
    }

    public function test_only_events_overlapping_the_range_are_returned(): void
    {
        $user = UserModel::factory()->create();
        $inside = $this->event('2030-03-10 10:00:00', '2030-03-10 14:00:00');
        $before = $this->event('2030-02-10 10:00:00', '2030-02-10 14:00:00');
        $after = $this->event('2030-04-01 00:00:00', '2030-04-01 05:00:00');
        $straddling = $this->event('2030-02-28 20:00:00', '2030-03-01 02:00:00');
        foreach ([$inside, $before, $after, $straddling] as $event) {
            $this->register($event, $user, RegistrationState::Confirmed);
        }

        $ids = array_map(static fn (CalendarEntryReferenceDTO $r): string => $r->id, $this->ask($user, '2030-03-01', '2030-04-01'));

        sort($ids);
        $expected = [$inside->id, $straddling->id];
        sort($expected);
        $this->assertSame($expected, $ids);
    }

    public function test_one_reference_per_event_and_the_query_count_does_not_grow(): void
    {
        $user = UserModel::factory()->create();
        foreach (range(1, 4) as $day) {
            $this->register($this->event("2030-03-0{$day} 10:00:00", "2030-03-0{$day} 12:00:00"), $user, RegistrationState::Confirmed);
        }

        DB::enableQueryLog();
        $references = $this->ask($user, '2030-03-01', '2030-04-01');
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(4, $references);
        $this->assertLessThanOrEqual(1, $queries);
    }

    private function event(string $start, string $end): EventModel
    {
        return EventModel::factory()->published()->create([
            'start_date' => new DateTimeImmutable($start),
            'end_date' => new DateTimeImmutable($end),
        ]);
    }

    private function register(EventModel $event, UserModel $user, RegistrationState $state): void
    {
        EventRegistrationModel::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'state' => $state,
        ]);
    }

    /**
     * @return array<CalendarEntryReferenceDTO>
     */
    private function ask(UserModel $user, string $from, string $to): array
    {
        return array_values($this->app->make(EventAttendanceSource::class)
            ->attendedEntries($user->id, new DateTimeImmutable($from), new DateTimeImmutable($to)));
    }
}
