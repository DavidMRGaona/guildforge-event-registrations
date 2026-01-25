<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\EventRegistrations\Domain\Enums\RegistrationState;

/**
 * @property string $id
 * @property string $event_id
 * @property string $user_id
 * @property RegistrationState $state
 * @property int|null $position
 * @property array<string, mixed>|null $form_data
 * @property string|null $notes
 * @property string|null $admin_notes
 * @property \Carbon\Carbon|null $confirmed_at
 * @property \Carbon\Carbon|null $cancelled_at
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read EventModel $event
 * @property-read UserModel $user
 */
final class EventRegistrationModel extends Model
{
    use HasUuids;

    protected $table = 'event_registrations_registrations';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'event_id',
        'user_id',
        'state',
        'position',
        'form_data',
        'notes',
        'admin_notes',
        'confirmed_at',
        'cancelled_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => RegistrationState::class,
            'position' => 'integer',
            'form_data' => 'array',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<EventModel, self>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(EventModel::class, 'event_id');
    }

    /**
     * @return BelongsTo<UserModel, self>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }
}
