<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Integration\Proposals;

use App\Application\Proposals\Services\EventProposalRequestRegistryInterface;
use App\Application\Proposals\Services\ProposalRequestValueRule;
use App\Domain\Proposals\Exceptions\ProposalRequestRefusedException;
use App\Domain\ValueObjects\EventId;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Modules\EventRegistrations\Application\Proposals\RegistrationsProposalRequest;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationConfigModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The registrations a person asks for when proposing an event (core#581).
 */
final class RegistrationsProposalRequestTest extends ModuleTestCase
{
    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    private RegistrationsProposalRequest $request;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('es');
        $this->request = $this->app->make(RegistrationsProposalRequest::class);
    }

    public function test_the_module_registers_its_request(): void
    {
        $registered = $this->app->make(EventProposalRequestRegistryInterface::class)->request('event-registrations.registrations');

        $this->assertInstanceOf(RegistrationsProposalRequest::class, $registered);
        $this->assertSame('¿Quieres inscripciones?', $registered->question());
        $this->assertTrue($registered->isAvailable());
    }

    public function test_the_waiting_list_only_shows_once_a_capacity_is_given(): void
    {
        $rule = new ProposalRequestValueRule;
        $fields = $this->request->fields();

        $this->assertSame(['capacity'], $rule->visibleFields($fields, []));
        $this->assertSame(['capacity', 'waiting_list'], $rule->visibleFields($fields, ['capacity' => 20]));

        $hidden = $rule->check($fields, ['capacity' => null, 'waiting_list' => true]);
        $this->assertTrue($hidden->isValid());
        $this->assertSame(['capacity' => null, 'waiting_list' => null], $hidden->values);
    }

    public function test_the_capacity_must_be_positive(): void
    {
        $result = (new ProposalRequestValueRule)->check($this->request->fields(), ['capacity' => 0]);

        $this->assertArrayHasKey('capacity', $result->errors);
    }

    public function test_applying_creates_the_configuration_with_the_values(): void
    {
        $event = EventModel::factory()->create();

        $this->request->apply(new EventId($event->id), ['capacity' => 20, 'waiting_list' => true]);

        $config = EventRegistrationConfigModel::query()->where('event_id', $event->id)->firstOrFail();
        $this->assertTrue($config->registration_enabled);
        $this->assertSame(20, $config->max_participants);
        $this->assertTrue($config->waiting_list_enabled);
        $this->assertNull($config->registration_opens_at);
        $this->assertFalse($config->requires_confirmation);
        $this->assertFalse($config->members_only);
    }

    public function test_applying_without_capacity_means_no_limit_and_no_waiting_list(): void
    {
        $event = EventModel::factory()->create();

        $this->request->apply(new EventId($event->id), ['capacity' => null, 'waiting_list' => null]);

        $config = EventRegistrationConfigModel::query()->where('event_id', $event->id)->firstOrFail();
        $this->assertTrue($config->registration_enabled);
        $this->assertNull($config->max_participants);
        $this->assertFalse($config->waiting_list_enabled);
    }

    public function test_applying_refuses_when_the_event_already_has_a_configuration(): void
    {
        $event = EventModel::factory()->create();
        EventRegistrationConfigModel::create(['event_id' => $event->id, 'registration_enabled' => true, 'max_participants' => 5]);

        try {
            $this->request->apply(new EventId($event->id), ['capacity' => 20, 'waiting_list' => true]);
            $this->fail('Expected the request to be refused.');
        } catch (ProposalRequestRefusedException $e) {
            $this->assertSame('El evento ya tiene configuradas las inscripciones.', $e->getMessage());
        }

        $this->assertSame(5, EventRegistrationConfigModel::query()->where('event_id', $event->id)->value('max_participants'));
    }

    public function test_it_describes_its_values(): void
    {
        $this->assertSame('Inscripciones con aforo de 20 y lista de espera', $this->request->describe(['capacity' => 20, 'waiting_list' => true]));
        $this->assertSame('Inscripciones con aforo de 20', $this->request->describe(['capacity' => 20, 'waiting_list' => false]));
        $this->assertSame('Inscripciones sin límite de aforo', $this->request->describe(['capacity' => null, 'waiting_list' => null]));
    }

    public function test_it_is_translated_to_english(): void
    {
        app()->setLocale('en');

        $this->assertSame('Registrations with a capacity of 20 and a waiting list', $this->request->describe(['capacity' => 20, 'waiting_list' => true]));
    }
}
