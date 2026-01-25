<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $event_id
 * @property bool $registration_enabled
 * @property int|null $max_participants
 * @property bool $waiting_list_enabled
 * @property int|null $max_waiting_list
 * @property \Carbon\Carbon|null $registration_opens_at
 * @property \Carbon\Carbon|null $registration_closes_at
 * @property \Carbon\Carbon|null $cancellation_deadline
 * @property bool $requires_confirmation
 * @property bool $requires_payment
 * @property bool $members_only
 * @property array<array{name: string, label: string, type: string, required: bool, options?: array<string>}>|null $custom_fields
 * @property string|null $confirmation_message
 * @property string|null $notification_email
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read EventModel $event
 */
final class EventRegistrationConfigModel extends Model
{
    protected $table = 'event_registrations_configs';

    protected $primaryKey = 'event_id';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * Default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'registration_enabled' => false,
        'waiting_list_enabled' => true,
        'requires_confirmation' => false,
        'requires_payment' => false,
        'members_only' => false,
    ];

    protected $fillable = [
        'event_id',
        'registration_enabled',
        'max_participants',
        'waiting_list_enabled',
        'max_waiting_list',
        'registration_opens_at',
        'registration_closes_at',
        'cancellation_deadline',
        'requires_confirmation',
        'requires_payment',
        'members_only',
        'custom_fields',
        'confirmation_message',
        'notification_email',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'registration_enabled' => 'boolean',
            'max_participants' => 'integer',
            'waiting_list_enabled' => 'boolean',
            'max_waiting_list' => 'integer',
            'registration_opens_at' => 'datetime',
            'registration_closes_at' => 'datetime',
            'cancellation_deadline' => 'datetime',
            'requires_confirmation' => 'boolean',
            'requires_payment' => 'boolean',
            'members_only' => 'boolean',
            'custom_fields' => 'array',
        ];
    }

    /**
     * @return BelongsTo<EventModel, self>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(EventModel::class, 'event_id');
    }
}
