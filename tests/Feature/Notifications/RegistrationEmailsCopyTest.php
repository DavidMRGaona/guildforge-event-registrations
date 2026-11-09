<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Feature\Notifications;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Notification;
use Modules\EventRegistrations\Application\Services\RegistrationNotificationServiceInterface;
use Modules\EventRegistrations\Domain\Entities\EventRegistration;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Domain\ValueObjects\EventRegistrationId;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationConfigModel;
use Modules\EventRegistrations\Notifications\RegistrationConfirmedNotification;
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
        $this->assertNull($message->toArray()['salutation']);
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

    public function test_every_confirmed_spot_email_carries_the_organisers_message(): void
    {
        $message = 'Trae tu ejército pintado';

        foreach ($this->confirmedSpotEmails($message) as $name => $mail) {
            $text = implode("\n", $mail->introLines);

            $this->assertStringContainsString('**Mensaje de quien organiza:** '.$message, $text, $name);
            $this->assertLessThan(
                array_search('Detalles del evento:', $mail->introLines, true),
                $this->lineOf($mail->introLines, $message),
                $name,
            );
        }
    }

    public function test_the_organisers_message_is_text_not_markdown(): void
    {
        foreach ($this->confirmedSpotEmails("Ver [aquí](http://x.test) **ya**\n<b>sala 2</b>") as $name => $mail) {
            $text = implode("\n", $mail->introLines);

            $this->assertStringContainsString('Ver \\[aquí\\]\\(http://x\\.test\\) \\*\\*ya\\*\\* <b>sala 2</b>', $text, $name);
            $this->assertStringNotContainsString("\n<b>", $text, $name);
        }
    }

    public function test_emails_without_a_message_or_for_other_states_do_not_mention_it(): void
    {
        foreach ($this->confirmedSpotEmails(null) as $name => $mail) {
            $this->assertStringNotContainsString('Mensaje de quien organiza', implode("\n", $mail->introLines), $name);
        }

        foreach ([RegistrationState::WaitingList, RegistrationState::Pending] as $state) {
            $mail = (new UserRegisteredNotification($this->event, $this->registration($state, 2), 'Trae tu ejército'))->toMail($this->user);

            $this->assertStringNotContainsString('Mensaje de quien organiza', implode("\n", $mail->introLines));
        }
    }

    public function test_the_notification_service_passes_the_stored_message_to_the_emails(): void
    {
        EventRegistrationConfigModel::create(['event_id' => $this->event->id, 'registration_enabled' => true, 'confirmation_message' => 'Sala 2']);
        Notification::fake();

        $service = app(RegistrationNotificationServiceInterface::class);
        $service->sendConfirmationEmail($this->registration(RegistrationState::Confirmed));
        $service->sendPromotionEmail($this->registration(RegistrationState::Confirmed));
        $service->sendRegistrationEmail($this->registration(RegistrationState::Confirmed));

        Notification::assertSentToTimes($this->user, RegistrationConfirmedNotification::class, 1);
        $sent = Notification::sent($this->user, RegistrationConfirmedNotification::class)->first();
        $this->assertStringContainsString('Sala 2', implode("\n", $sent->toMail($this->user)->introLines));
        $this->assertStringContainsString('Sala 2', implode("\n", Notification::sent($this->user, WaitingListPromotedNotification::class)->first()->toMail($this->user)->introLines));
        $this->assertStringContainsString('Sala 2', implode("\n", Notification::sent($this->user, UserRegisteredNotification::class)->first()->toMail($this->user)->introLines));
    }

    /**
     * @return array<string, MailMessage>
     */
    private function confirmedSpotEmails(?string $message): array
    {
        $confirmed = $this->registration(RegistrationState::Confirmed);

        return [
            'confirmed' => (new RegistrationConfirmedNotification($this->event, $confirmed, $message))->toMail($this->user),
            'promoted' => (new WaitingListPromotedNotification($this->event, $confirmed, $message))->toMail($this->user),
            'registered' => (new UserRegisteredNotification($this->event, $confirmed, $message))->toMail($this->user),
        ];
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function lineOf(array $lines, string $needle): int
    {
        foreach ($lines as $index => $line) {
            if (str_contains($line, $needle)) {
                return $index;
            }
        }

        return PHP_INT_MAX;
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
