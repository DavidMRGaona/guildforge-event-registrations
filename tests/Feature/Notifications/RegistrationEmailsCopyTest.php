<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Feature\Notifications;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Modules\EventRegistrations\Domain\Entities\EventRegistration;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Domain\ValueObjects\EventRegistrationId;
use Modules\EventRegistrations\Notifications\UserRegisteredNotification;
use Modules\EventRegistrations\Notifications\WaitingListPromotedNotification;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The registration emails say what happened, in the site's Spanish style.
 */
final class RegistrationEmailsCopyTest extends ModuleTestCase
{
    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    private UserModel $user;

    private EventModel $event;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('es');
        $this->user = UserModel::factory()->create(['name' => 'John']);
        $this->event = EventModel::factory()->published()->create(['title' => 'Jornada de rol', 'start_date' => now()->addWeek()]);
    }

    public function test_a_confirmed_registration_does_not_say_it_was_only_received(): void
    {
        $message = (new UserRegisteredNotification($this->event, $this->registration(RegistrationState::Confirmed)))->toMail($this->user);
        $text = implode(' ', $message->introLines);

        $this->assertSame('Inscripción confirmada: Jornada de rol', $message->subject);
        $this->assertStringContainsString('«Jornada de rol»', $text);
        $this->assertStringContainsString('confirmada', $text);
        $this->assertStringNotContainsString('recibida', $text);
        $this->assertSame('¡Hola, John!', $message->greeting);
        $this->assertNull($message->salutation);
    }

    public function test_a_waiting_list_registration_says_so_with_the_position(): void
    {
        $message = (new UserRegisteredNotification($this->event, $this->registration(RegistrationState::WaitingList, 3)))->toMail($this->user);
        $text = implode(' ', $message->introLines);

        $this->assertSame('Lista de espera: Jornada de rol', $message->subject);
        $this->assertStringContainsString('lista de espera', $text);
        $this->assertStringContainsString('**3**', $text);
    }

    public function test_a_pending_registration_is_received_and_waits_for_confirmation(): void
    {
        $message = (new UserRegisteredNotification($this->event, $this->registration(RegistrationState::Pending)))->toMail($this->user);
        $text = implode(' ', $message->introLines);

        $this->assertSame('Inscripción recibida: Jornada de rol', $message->subject);
        $this->assertStringContainsString('pendiente de confirmación', $text);
    }

    public function test_the_promotion_subject_reads_naturally(): void
    {
        $message = (new WaitingListPromotedNotification($this->event, $this->registration(RegistrationState::Confirmed)))->toMail($this->user);

        $this->assertSame('¡Plaza disponible en Jornada de rol!', $message->subject);
        $this->assertStringContainsString('«Jornada de rol»', implode(' ', $message->introLines));
    }

    private function registration(RegistrationState $state, ?int $position = null): EventRegistration
    {
        return new EventRegistration(
            id: EventRegistrationId::generate(),
            eventId: $this->event->id,
            userId: $this->user->id,
            state: $state,
            position: $position,
        );
    }
}
