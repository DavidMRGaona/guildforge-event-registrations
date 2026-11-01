<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Feature\Filament;

use App\Application\Surveys\Contracts\SubjectEndSurveyChoiceInterface;
use App\Application\Surveys\DTOs\EndSurveyChoiceDTO;
use App\Application\Surveys\DTOs\EndSurveyOptionDTO;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Livewire\Livewire;
use Modules\EventRegistrations\EventRegistrationsServiceProvider;
use Modules\EventRegistrations\Infrastructure\Surveys\EventSurveySubjectSource;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;
use Tests\Support\Surveys\FakeSubjectEndSurveyChoice;

/**
 * The event form carries the core's "survey when it ends" field for the event subject type,
 * without knowing the surveys module: it shows only when that module offers something.
 */
final class EventEndSurveyFieldTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    private EventModel $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel();
        $this->actingAs(UserModel::factory()->admin()->create());

        $this->event = EventModel::factory()->create([
            'start_date' => now()->addDays(10)->setTime(10, 0),
            'end_date' => now()->addDays(10)->setTime(20, 0),
        ]);
        $this->event->tags()->attach(TagModel::factory()->forEvents()->create());
    }

    public function test_without_the_surveys_module_the_event_form_has_no_end_survey(): void
    {
        Livewire::test(EditEvent::class, ['record' => $this->event->getRouteKey()])
            ->assertFormFieldIsHidden('end_survey');
    }

    public function test_the_event_form_chooses_the_end_survey_of_the_event(): void
    {
        $fake = new FakeSubjectEndSurveyChoice(new EndSurveyChoiceDTO(
            options: [new EndSurveyOptionDTO('type-default', 'Plantilla del tipo: Valoración'), new EndSurveyOptionDTO('none', 'Ninguna')],
            selected: 'type-default',
        ));
        $this->app->instance(SubjectEndSurveyChoiceInterface::class, $fake);

        Livewire::test(EditEvent::class, ['record' => $this->event->getRouteKey()])
            ->assertFormFieldIsVisible('end_survey')
            ->fillForm(['end_survey' => 'none'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertCount(1, $fake->chosen);
        $this->assertSame(EventSurveySubjectSource::TYPE, $fake->chosen[0]['sourceType']);
        $this->assertSame((string) $this->event->id, $fake->chosen[0]['subjectId']);
        $this->assertSame('none', $fake->chosen[0]['selection']->value);
    }

    public function test_booting_the_module_again_does_not_add_the_field_twice(): void
    {
        $sections = count(EventResource::getExtendedFormSections());

        $provider = $this->app->getProvider(EventRegistrationsServiceProvider::class);
        $this->assertInstanceOf(EventRegistrationsServiceProvider::class, $provider);
        $provider->boot();

        $this->assertCount($sections, EventResource::getExtendedFormSections());
        $this->assertArrayHasKey('event-registrations.end-survey', EventResource::getExtendedFormSections());
    }
}
