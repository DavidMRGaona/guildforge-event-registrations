<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Integration\Surveys;

use App\Application\Surveys\DTOs\SurveyAudienceDTO;
use App\Application\Surveys\DTOs\SurveySubjectDTO;
use App\Application\Surveys\Enums\SurveySubjectStatus;
use App\Application\Surveys\Services\SurveySubjectSourceRegistryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use Illuminate\Support\Str;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Domain\Services\Clock;
use Modules\EventRegistrations\Infrastructure\Surveys\EventSurveySubjectSource;
use Modules\EventRegistrations\Tests\Support\BuildsSurveyableEvents;
use Modules\EventRegistrations\Tests\Support\FixedClock;
use Tests\Support\Modules\ModuleTestCase;

/**
 * An event with registrations as something a survey can be about: its snapshot, who is asked to
 * answer and who manages its surveys.
 */
final class EventSurveySubjectSourceTest extends ModuleTestCase
{
    use BuildsSurveyableEvents;

    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useUtcStorage();
        $this->app->instance(Clock::class, FixedClock::at('2030-01-05T12:00:00+00:00'));
    }

    public function test_the_registry_offers_events(): void
    {
        $source = $this->app->make(SurveySubjectSourceRegistryInterface::class)->source('event');

        $this->assertInstanceOf(EventSurveySubjectSource::class, $source);
        $this->assertSame('event', $source->sourceType());
        $this->assertSame('Evento', $source->sourceLabel());
    }

    public function test_find_returns_the_event_snapshot(): void
    {
        $manager = $this->userWithPermission(EventSurveySubjectSource::MANAGE_PERMISSION);
        $this->userWithPermission('events.update');
        $event = $this->surveyableEvent('2030-01-10T16:00:00+00:00', '2030-01-10T20:00:00+00:00', ['members_only' => true]);

        $subject = $this->source()->find($event->id);

        $this->assertInstanceOf(SurveySubjectDTO::class, $subject);
        $this->assertSame('event', $subject->sourceType);
        $this->assertSame($event->id, $subject->subjectId);
        $this->assertSame('Evento de ejemplo', $subject->title);
        $this->assertEquals(new DateTimeImmutable('2030-01-10T16:00:00+00:00'), $subject->startsAt);
        $this->assertEquals(new DateTimeImmutable('2030-01-10T20:00:00+00:00'), $subject->endsAt);
        $this->assertSame([$manager->id], $subject->organizerIds);
        $this->assertSame(route('events.show', $event->slug), $subject->url);
        $this->assertCount(1, $subject->attributes);
        $this->assertSame('members_only', $subject->attributes[0]->key);
        $this->assertSame('Solo socios', $subject->attributes[0]->label);
        $this->assertTrue($subject->attributes[0]->value);
    }

    public function test_find_reports_active_before_the_end_and_ended_after_it(): void
    {
        $running = $this->surveyableEvent('2030-01-05T10:00:00+00:00', '2030-01-05T12:00:01+00:00');
        $ended = $this->surveyableEvent('2030-01-05T10:00:00+00:00', '2030-01-05T12:00:00+00:00');

        $this->assertSame(SurveySubjectStatus::Active, $this->source()->find($running->id)?->status);
        $this->assertSame(SurveySubjectStatus::Ended, $this->source()->find($ended->id)?->status);
    }

    public function test_find_ignores_unpublished_events_events_without_registrations_and_unknown_ids(): void
    {
        $unpublished = $this->surveyableEvent('2030-01-10T16:00:00+00:00', '2030-01-10T20:00:00+00:00', published: false);
        $disabled = $this->surveyableEvent('2030-01-10T16:00:00+00:00', '2030-01-10T20:00:00+00:00', ['registration_enabled' => false]);
        $withoutConfig = EventModel::factory()->published()->create();

        $this->assertNull($this->source()->find($unpublished->id));
        $this->assertNull($this->source()->find($disabled->id));
        $this->assertNull($this->source()->find($withoutConfig->id));
        $this->assertNull($this->source()->find(Str::uuid()->toString()));
        $this->assertNull($this->source()->find('not-a-uuid'));
    }

    public function test_audience_lists_confirmed_registrations_only(): void
    {
        $event = $this->surveyableEvent('2030-01-10T16:00:00+00:00', '2030-01-10T20:00:00+00:00');
        $confirmed = $this->registrant($event);
        foreach ([RegistrationState::Pending, RegistrationState::WaitingList, RegistrationState::Cancelled, RegistrationState::Rejected] as $state) {
            $this->registrant($event, $state);
        }

        $audience = $this->source()->audience($event->id);

        $this->assertSame([$confirmed->id], $audience->userIds);
        $this->assertSame([], $audience->guests);
    }

    public function test_audience_leaves_out_anonymized_accounts(): void
    {
        $event = $this->surveyableEvent('2030-01-10T16:00:00+00:00', '2030-01-10T20:00:00+00:00');
        $kept = $this->registrant($event);
        $gone = $this->registrant($event);
        UserModel::query()->whereKey($gone->id)->update(['anonymized_at' => now()]);

        $this->assertSame([$kept->id], $this->source()->audience($event->id)->userIds);
    }

    public function test_audience_of_an_event_not_offered_is_empty(): void
    {
        $event = $this->surveyableEvent('2030-01-10T16:00:00+00:00', '2030-01-10T20:00:00+00:00', published: false);
        $this->registrant($event);

        $this->assertEquals(SurveyAudienceDTO::empty(), $this->source()->audience($event->id));
        $this->assertEquals(SurveyAudienceDTO::empty(), $this->source()->audience('not-a-uuid'));
    }

    public function test_only_holders_of_the_survey_permission_can_manage(): void
    {
        $event = $this->surveyableEvent('2030-01-10T16:00:00+00:00', '2030-01-10T20:00:00+00:00');
        $hidden = $this->surveyableEvent('2030-01-10T16:00:00+00:00', '2030-01-10T20:00:00+00:00', published: false);
        $manager = $this->userWithPermission(EventSurveySubjectSource::MANAGE_PERMISSION);
        $eventEditor = $this->userWithPermission('events.update');
        $registrant = $this->registrant($event);

        $this->assertTrue($this->source()->canManage($manager->id, $event->id));
        $this->assertFalse($this->source()->canManage($eventEditor->id, $event->id));
        $this->assertFalse($this->source()->canManage($registrant->id, $event->id));
        $this->assertFalse($this->source()->canManage(Str::uuid()->toString(), $event->id));
        $this->assertFalse($this->source()->canManage($manager->id, $hidden->id));
    }

    private function source(): EventSurveySubjectSource
    {
        return $this->app->make(EventSurveySubjectSource::class);
    }
}
