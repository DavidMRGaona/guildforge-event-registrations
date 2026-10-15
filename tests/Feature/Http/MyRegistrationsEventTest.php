<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Feature\Http;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\DB;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * GET /mis-inscripciones carries what the profile section shows of each event (title, dates,
 * place and link), only for published events, without one query per registration.
 */
final class MyRegistrationsEventTest extends ModuleTestCase
{
    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    private UserModel $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = UserModel::factory()->create();
    }

    public function test_each_registration_carries_its_published_event(): void
    {
        $event = EventModel::factory()->published()->create([
            'title' => 'Torneo de primavera',
            'slug' => 'torneo-de-primavera',
            'start_date' => '2030-04-12 10:00:00',
            'end_date' => '2030-04-12 20:00:00',
            'location' => 'Local de la asociación',
        ]);
        $registration = $this->register($event, RegistrationState::WaitingList, position: 2);

        $this->actingAs($this->user)
            ->getJson('/mis-inscripciones')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $registration->id)
            ->assertJsonPath('data.0.event_id', $event->id)
            ->assertJsonPath('data.0.state', 'waiting_list')
            ->assertJsonPath('data.0.position', 2)
            ->assertJsonPath('data.0.event', [
                'id' => $event->id,
                'title' => 'Torneo de primavera',
                'slug' => 'torneo-de-primavera',
                'start_date' => $event->start_date->format('c'),
                'end_date' => $event->end_date->format('c'),
                'location' => 'Local de la asociación',
                'url' => '/eventos/torneo-de-primavera',
            ]);
    }

    public function test_the_link_of_the_event_opens_its_page(): void
    {
        $event = EventModel::factory()->published()->create(['slug' => 'partida-abierta']);
        $this->register($event, RegistrationState::Confirmed);

        $url = $this->actingAs($this->user)->getJson('/mis-inscripciones')->json('data.0.event.url');

        $this->assertIsString($url);
        $this->get($url)->assertOk();
    }

    public function test_an_unpublished_event_is_not_described(): void
    {
        $event = EventModel::factory()->draft()->create(['title' => 'Borrador secreto']);
        $this->register($event, RegistrationState::Confirmed);

        $response = $this->actingAs($this->user)
            ->getJson('/mis-inscripciones')
            ->assertOk()
            ->assertJsonPath('data.0.event_id', $event->id)
            ->assertJsonPath('data.0.event', null);

        $this->assertStringNotContainsString('Borrador secreto', (string) $response->getContent());
    }

    public function test_the_number_of_queries_does_not_grow_with_the_registrations(): void
    {
        $this->register(EventModel::factory()->published()->create(), RegistrationState::Confirmed);
        $withOne = $this->queriesForMyRegistrations();

        foreach (range(1, 4) as $ignored) {
            $this->register(EventModel::factory()->published()->create(), RegistrationState::Confirmed);
        }
        $withFive = $this->queriesForMyRegistrations();

        $this->assertSame($withOne, $withFive);
    }

    private function queriesForMyRegistrations(): int
    {
        $this->actingAs($this->user);
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson('/mis-inscripciones')->assertOk();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    private function register(EventModel $event, RegistrationState $state, ?int $position = null): EventRegistrationModel
    {
        return EventRegistrationModel::create([
            'event_id' => $event->id,
            'user_id' => $this->user->id,
            'state' => $state,
            'position' => $position,
            'confirmed_at' => $state === RegistrationState::Confirmed ? now() : null,
        ]);
    }
}
