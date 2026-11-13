<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Integration\Proposals;

use App\Domain\Proposals\Enums\ProposalStatus;
use App\Domain\Surveys\Events\SurveySubjectCancelled;
use App\Domain\Surveys\Events\SurveySubjectEnded;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Modules\EventRegistrations\Console\Commands\SendEventRemindersCommand;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Domain\Services\Clock;
use Modules\EventRegistrations\Infrastructure\Calendar\EventAttendanceSource;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationConfigModel;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationModel;
use Modules\EventRegistrations\Infrastructure\Services\RegistrationQueryService;
use Modules\EventRegistrations\Infrastructure\Surveys\EndedEventAnnouncer;
use Modules\EventRegistrations\Infrastructure\Surveys\SurveyableEvents;
use Modules\EventRegistrations\Notifications\EventReminderNotification;
use Modules\EventRegistrations\Tests\Support\FixedClock;
use Tests\Support\Modules\ModuleTestCase;
use Tests\Support\Time\UsesUtcStorage;

/**
 * An event proposed from the web and not approved yet is an unpublished event row: nothing of the
 * module may treat it as a real event, even with registrations forged onto it (core#581).
 */
final class UnapprovedProposalsTest extends ModuleTestCase
{
    use UsesUtcStorage;

    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useUtcStorage();
        $this->now = CarbonImmutable::parse('2030-01-05 12:00:00', 'UTC');
        $this->travelTo($this->now);
    }

    /**
     * @return array{EventModel, UserModel}
     */
    private function proposalWithRegistrant(ProposalStatus $status, CarbonImmutable $startsAt, CarbonImmutable $endsAt): array
    {
        $event = EventModel::factory()->proposal($status)->create([
            'start_date' => $startsAt,
            'end_date' => $endsAt,
        ]);
        EventRegistrationConfigModel::create(['event_id' => $event->id, 'registration_enabled' => true]);

        $user = UserModel::factory()->create();
        EventRegistrationModel::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'state' => RegistrationState::Confirmed,
            'confirmed_at' => $this->now->subDay(),
        ]);

        return [$event, $user];
    }

    /**
     * @return list<ProposalStatus>
     */
    private function unapproved(): array
    {
        return [ProposalStatus::PendingReview, ProposalStatus::Rejected, ProposalStatus::Draft];
    }

    public function test_no_reminder_goes_out_for_an_unapproved_proposal(): void
    {
        Notification::fake();
        config()->set('modules.settings.event-registrations.send_reminder_email', true);
        $this->app->make(Kernel::class)->registerCommand($this->app->make(SendEventRemindersCommand::class));

        foreach ($this->unapproved() as $status) {
            $this->proposalWithRegistrant($status, $this->now->addHours(5), $this->now->addHours(9));
        }

        $this->artisan('event-registrations:send-reminders')->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertSame(0, EventRegistrationModel::query()->whereNotNull('reminder_sent_at')->count());
    }

    public function test_an_unapproved_proposal_is_not_a_survey_subject_and_never_ends(): void
    {
        Event::fake([SurveySubjectEnded::class]);
        $this->app->instance(Clock::class, new FixedClock(new DateTimeImmutable('2030-01-05T12:00:00+00:00')));

        foreach ($this->unapproved() as $status) {
            [$event] = $this->proposalWithRegistrant($status, $this->now->subHours(6), $this->now->subHours(2));

            $this->assertNull($this->app->make(SurveyableEvents::class)->find($event->id));
        }

        $this->assertSame(0, $this->app->make(EndedEventAnnouncer::class)->announce());
        Event::assertNotDispatched(SurveySubjectEnded::class);
    }

    public function test_an_unapproved_proposal_is_not_in_the_personal_calendar(): void
    {
        [, $user] = $this->proposalWithRegistrant(ProposalStatus::PendingReview, $this->now->addDay(), $this->now->addDay()->addHours(3));

        $entries = $this->app->make(EventAttendanceSource::class)->attendedEntries(
            $user->id,
            new DateTimeImmutable('2030-01-01T00:00:00+00:00'),
            new DateTimeImmutable('2030-02-01T00:00:00+00:00'),
        );

        $this->assertSame([], $entries);
    }

    public function test_an_unapproved_proposal_is_not_listed_in_my_registrations(): void
    {
        [, $user] = $this->proposalWithRegistrant(ProposalStatus::PendingReview, $this->now->addDay(), $this->now->addDay()->addHours(3));

        $rows = $this->app->make(RegistrationQueryService::class)->getUserRegistrationsWithEvents($user->id);

        foreach ($rows as $row) {
            $this->assertNull($row->event);
        }
    }

    public function test_deleting_an_unapproved_proposal_cancels_no_survey_subject(): void
    {
        Event::fake([SurveySubjectCancelled::class]);

        foreach ($this->unapproved() as $status) {
            [$event] = $this->proposalWithRegistrant($status, $this->now->addDays(3), $this->now->addDays(3)->addHours(3));
            $event->delete();
        }

        Event::assertNotDispatched(SurveySubjectCancelled::class);
    }

    public function test_deleting_an_approved_event_still_cancels_its_survey_subject(): void
    {
        Event::fake([SurveySubjectCancelled::class]);
        [$event] = $this->proposalWithRegistrant(ProposalStatus::Approved, $this->now->addDays(3), $this->now->addDays(3)->addHours(3));

        $event->delete();

        Event::assertDispatchedTimes(SurveySubjectCancelled::class, 1);
    }

    public function test_the_reminder_still_goes_out_for_an_approved_proposal(): void
    {
        Notification::fake();
        config()->set('modules.settings.event-registrations.send_reminder_email', true);
        $this->app->make(Kernel::class)->registerCommand($this->app->make(SendEventRemindersCommand::class));
        [, $user] = $this->proposalWithRegistrant(ProposalStatus::Approved, $this->now->addHours(5), $this->now->addHours(9));

        $this->artisan('event-registrations:send-reminders')->assertSuccessful();

        Notification::assertSentTo($user, EventReminderNotification::class);
    }
}
