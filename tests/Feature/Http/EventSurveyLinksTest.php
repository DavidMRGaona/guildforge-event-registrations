<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Feature\Http;

use App\Application\Surveys\Contracts\SurveySubjectLinksInterface;
use App\Application\Surveys\DTOs\SurveyLinkDTO;
use App\Application\Surveys\DTOs\SurveySubjectLinksDTO;
use App\Application\Surveys\Enums\SurveyLinkAction;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use ArrayObject;
use Modules\EventRegistrations\Tests\Support\BuildsSurveyableEvents;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The event page links to its surveys through the core's contract, whatever module answers it:
 * nothing shows without one.
 */
final class EventSurveyLinksTest extends ModuleTestCase
{
    use BuildsSurveyableEvents;

    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    /** @var ArrayObject<int, array{string, string, ?string}> what the endpoint asked the contract */
    private ArrayObject $asked;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useUtcStorage();
        $this->asked = new ArrayObject;
    }

    public function test_the_endpoint_returns_what_the_contract_links_for_the_person(): void
    {
        $event = $this->surveyableEvent('2030-01-10T16:00:00+00:00', '2030-01-10T20:00:00+00:00');
        $user = $this->registrant($event);
        $this->fakeLinks(new SurveySubjectLinksDTO(
            manageUrl: 'https://example.com/encuestas/sujeto/event/'.$event->id,
            surveys: [
                new SurveyLinkDTO('Valoración del evento', 'https://example.com/encuestas/1', SurveyLinkAction::Respond),
                new SurveyLinkDTO('Encuesta anterior', 'https://example.com/encuestas/2', SurveyLinkAction::ViewResults),
            ],
        ));

        $this->actingAs($user)->getJson($this->url($event->id))
            ->assertOk()
            ->assertExactJson(['data' => [
                'manageUrl' => 'https://example.com/encuestas/sujeto/event/'.$event->id,
                'surveys' => [
                    ['title' => 'Valoración del evento', 'url' => 'https://example.com/encuestas/1', 'action' => 'respond'],
                    ['title' => 'Encuesta anterior', 'url' => 'https://example.com/encuestas/2', 'action' => 'view_results'],
                ],
            ]]);

        $this->assertSame([['event', $event->id, $user->id]], $this->asked->getArrayCopy());
    }

    public function test_nothing_to_link_gives_null(): void
    {
        $event = $this->surveyableEvent('2030-01-10T16:00:00+00:00', '2030-01-10T20:00:00+00:00');
        $this->fakeLinks(new SurveySubjectLinksDTO);

        $this->actingAs(UserModel::factory()->create())->getJson($this->url($event->id))
            ->assertOk()
            ->assertExactJson(['data' => null]);
    }

    public function test_without_the_surveys_module_there_are_no_links(): void
    {
        $event = $this->surveyableEvent('2030-01-10T16:00:00+00:00', '2030-01-10T20:00:00+00:00');

        $this->actingAs(UserModel::factory()->create())->getJson($this->url($event->id))
            ->assertOk()
            ->assertExactJson(['data' => null]);
    }

    public function test_an_event_not_offered_gives_null_and_never_asks_the_contract(): void
    {
        $hidden = $this->surveyableEvent('2030-01-10T16:00:00+00:00', '2030-01-10T20:00:00+00:00', published: false);
        $disabled = $this->surveyableEvent('2030-01-10T16:00:00+00:00', '2030-01-10T20:00:00+00:00', ['registration_enabled' => false]);
        $this->fakeLinks(new SurveySubjectLinksDTO(manageUrl: 'https://example.com/encuestas'));
        $user = UserModel::factory()->create();

        $this->actingAs($user)->getJson($this->url($hidden->id))->assertOk()->assertExactJson(['data' => null]);
        $this->actingAs($user)->getJson($this->url($disabled->id))->assertOk()->assertExactJson(['data' => null]);

        $this->assertSame([], $this->asked->getArrayCopy());
    }

    public function test_a_visitor_gets_no_links(): void
    {
        $event = $this->surveyableEvent('2030-01-10T16:00:00+00:00', '2030-01-10T20:00:00+00:00');
        $this->fakeLinks(new SurveySubjectLinksDTO(manageUrl: 'https://example.com/encuestas'));

        $this->getJson($this->url($event->id))->assertUnauthorized();

        $this->assertSame([], $this->asked->getArrayCopy());
    }

    public function test_an_id_that_is_not_a_uuid_is_not_found(): void
    {
        $this->actingAs(UserModel::factory()->create())->getJson('/eventos/no-es-un-uuid/encuestas')->assertNotFound();
    }

    private function url(string $eventId): string
    {
        return route('event-registrations.surveys', ['eventId' => $eventId]);
    }

    private function fakeLinks(SurveySubjectLinksDTO $links): void
    {
        $this->app->instance(SurveySubjectLinksInterface::class, new readonly class($links, $this->asked) implements SurveySubjectLinksInterface
        {
            /**
             * @param  ArrayObject<int, array{string, string, ?string}>  $asked
             */
            public function __construct(private SurveySubjectLinksDTO $links, private ArrayObject $asked) {}

            public function forSubject(string $sourceType, string $subjectId, ?string $userId): SurveySubjectLinksDTO
            {
                $this->asked[] = [$sourceType, $subjectId, $userId];

                return $this->links;
            }
        });
    }
}
