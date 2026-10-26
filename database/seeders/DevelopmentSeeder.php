<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Database\Seeders;

use App\Application\Authorization\Services\MemberRoleServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationConfigModel;
use Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models\EventRegistrationModel;

final class DevelopmentSeeder extends Seeder
{
    private const int DEMO_EVENT_COUNT = 3;

    public function run(): void
    {
        // Demo data: the core runs every module seeder on install and update, production included
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        // Enabling the module in tests runs this seeder too, and the suites expect no registrations
        if (! app()->environment('local')) {
            return;
        }

        $people = $this->samplePeople();
        $events = $this->upcomingEvents();

        // Registrations are written straight to the tables, so no confirmation or
        // waiting-list email is sent
        $this->seedFullEventWithWaitingList($events[0], $people);
        $this->seedEventWithConfirmationAndCancellation($events[1], $people);
        $this->seedMembersOnlyEvent($events[2], $people);
    }

    /**
     * Event with a capacity of three: all taken, so a fourth person waits.
     *
     * @param  array<string, UserModel>  $people
     */
    private function seedFullEventWithWaitingList(EventModel $event, array $people): void
    {
        $this->config($event, [
            'registration_enabled' => true,
            'max_participants' => 3,
            'waiting_list_enabled' => true,
            'max_waiting_list' => 5,
            'registration_opens_at' => now()->subDays(10),
            'registration_closes_at' => $event->start_date->copy()->subDay(),
            'cancellation_deadline' => $event->start_date->copy()->subDays(2),
            'confirmation_message' => 'Tu plaza está confirmada. Te esperamos en el local de la asociación.',
            'custom_fields' => [
                ['name' => 'experience', 'label' => 'Experiencia previa', 'type' => 'select', 'required' => true, 'options' => ['Ninguna', 'Alguna', 'Mucha']],
                ['name' => 'comments', 'label' => 'Comentarios', 'type' => 'textarea', 'required' => false],
            ],
        ]);

        foreach (['john' => 'Alguna', 'jane' => 'Mucha', 'richard' => 'Ninguna'] as $key => $experience) {
            $this->registration($event, $people[$key], RegistrationState::Confirmed, formData: ['experience' => $experience]);
        }

        $this->registration($event, $people['mary'], RegistrationState::WaitingList, position: 1, formData: ['experience' => 'Alguna'], notes: 'Me apunto a la lista de espera por si queda una plaza libre.');
    }

    /**
     * Event where the organisers review every registration: one pending, one confirmed,
     * one cancelled by the user and one rejected.
     *
     * @param  array<string, UserModel>  $people
     */
    private function seedEventWithConfirmationAndCancellation(EventModel $event, array $people): void
    {
        $this->config($event, [
            'registration_enabled' => true,
            'max_participants' => 12,
            'waiting_list_enabled' => false,
            'requires_confirmation' => true,
            'registration_opens_at' => now()->subDays(5),
            'cancellation_deadline' => $event->start_date->copy()->subDay(),
            'notification_email' => 'inscripciones@example.local',
        ]);

        $this->registration($event, $people['richard'], RegistrationState::Pending, notes: 'Voy con mi grupo habitual.');
        $this->registration($event, $people['jane'], RegistrationState::Confirmed);
        $this->registration($event, $people['john'], RegistrationState::Cancelled, notes: 'Al final no puedo asistir.');
        $this->registration($event, $people['mary'], RegistrationState::Rejected, adminNotes: 'Inscripción duplicada con otra cuenta.');
    }

    /**
     * Event reserved to association members: the user needs the association role to register.
     *
     * @param  array<string, UserModel>  $people
     */
    private function seedMembersOnlyEvent(EventModel $event, array $people): void
    {
        $this->config($event, [
            'registration_enabled' => true,
            'max_participants' => 20,
            'waiting_list_enabled' => true,
            'members_only' => true,
            'registration_opens_at' => now()->subDays(2),
            'confirmation_message' => 'Gracias por inscribirte, socio. Recuerda traer tu carné.',
        ]);

        $this->registration($event, $people['john'], RegistrationState::Confirmed);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function config(EventModel $event, array $values): void
    {
        EventRegistrationConfigModel::updateOrCreate(['event_id' => $event->id], $values);
    }

    /**
     * @param  array<string, mixed>  $formData
     */
    private function registration(
        EventModel $event,
        UserModel $user,
        RegistrationState $state,
        ?int $position = null,
        array $formData = [],
        ?string $notes = null,
        ?string $adminNotes = null,
    ): void {
        EventRegistrationModel::updateOrCreate(
            ['event_id' => $event->id, 'user_id' => $user->id],
            [
                'state' => $state,
                'position' => $state === RegistrationState::WaitingList ? $position : null,
                'form_data' => $formData,
                'notes' => $notes,
                'admin_notes' => $adminNotes,
                'confirmed_at' => $state === RegistrationState::Confirmed ? now()->subDays(3) : null,
                'cancelled_at' => $state === RegistrationState::Cancelled ? now()->subDay() : null,
            ],
        );
    }

    /**
     * The first upcoming published events the core seeders created; demo events fill the gap
     * when there are fewer than needed.
     *
     * @return list<EventModel>
     */
    private function upcomingEvents(): array
    {
        $missing = self::DEMO_EVENT_COUNT - EventModel::where('is_published', true)->where('start_date', '>', now())->count();

        for ($i = 1; $i <= $missing; $i++) {
            $title = 'Jornada de inscripciones '.$i;

            $event = EventModel::firstOrNew(['slug' => Str::slug($title)]);

            if (! $event->exists) {
                $start = now()->addDays(7 * $i)->setTime(18, 0);
                $event->forceFill([
                    'title' => $title,
                    'description' => 'Jornada de ejemplo para probar las inscripciones a eventos.',
                    'start_date' => $start,
                    'end_date' => $start->copy()->addHours(4),
                    'location' => 'Sala principal',
                    'is_published' => true,
                ])->save();
            }
        }

        return array_values(EventModel::where('is_published', true)
            ->where('start_date', '>', now())
            ->orderBy('start_date')
            ->limit(self::DEMO_EVENT_COUNT)
            ->get()
            ->all());
    }

    /**
     * @return array<string, UserModel>
     */
    private function samplePeople(): array
    {
        $memberRole = app(MemberRoleServiceInterface::class);
        $people = [];

        foreach (['john' => 'John Doe', 'jane' => 'Jane Doe', 'richard' => 'Richard Roe', 'mary' => 'Mary Major'] as $key => $name) {
            $email = Str::lower(str_replace(' ', '.', $name)).'@example.local';
            $user = UserModel::where('email', $email)->first();

            if ($user === null) {
                $user = new UserModel;
                $user->forceFill([
                    'name' => $name,
                    'display_name' => $name,
                    'email' => $email,
                    'email_verified_at' => now(),
                    'password' => Hash::make('password'),
                ])->save();
            }

            $memberRole->assignTo($user);
            $people[$key] = $user;
        }

        return $people;
    }
}
