<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Listeners;

use App\Infrastructure\Authorization\Services\PermissionHolderFinder;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Log;
use Modules\EventRegistrations\Domain\Events\UserRegisteredToEvent;
use Modules\EventRegistrations\Notifications\NewRegistrationToManageNotification;
use Throwable;

/**
 * A new registration, in the bell of whoever manages registrations (event-registrations#114). The
 * mail to the address saved in the event goes out apart and is not affected. Never tells the
 * person who signed up, and says nothing about an event already over, like that mail.
 */
final readonly class NotifyRegistrationManagers
{
    public function __construct(
        private PermissionHolderFinder $holders,
    ) {}

    public function handle(UserRegisteredToEvent $event): void
    {
        $model = EventModel::query()->find($event->eventId);
        $registrant = UserModel::query()->find($event->userId);

        if (! $model instanceof EventModel || ! $registrant instanceof UserModel || $this->isOver($model)) {
            return;
        }

        $notification = new NewRegistrationToManageNotification(
            eventId: $event->eventId,
            registrationId: $event->registrationId,
            registrantId: $event->userId,
            registrantName: $registrant->display_name ?? $registrant->name,
            state: $event->state,
            position: $event->position,
        );

        $this->holders
            ->usersWhoCan(...NewRegistrationToManageNotification::MANAGE_PERMISSIONS)
            ->reject(fn (UserModel $manager): bool => (string) $manager->getKey() === $event->userId)
            ->each(function (UserModel $manager) use ($notification, $event): void {
                try {
                    $manager->notify($notification);
                } catch (Throwable $exception) {
                    // Signing up already happened: a notice that cannot be queued must not undo it
                    Log::error('Could not queue the new registration notice for a manager.', [
                        'user_id' => $manager->getKey(),
                        'registration_id' => $event->registrationId,
                        'exception' => $exception,
                    ]);
                }
            });
    }

    private function isOver(EventModel $event): bool
    {
        return ($event->end_date ?? $event->start_date)->isPast();
    }
}
