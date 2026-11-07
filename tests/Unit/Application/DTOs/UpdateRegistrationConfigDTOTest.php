<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Unit\Application\DTOs;

use Modules\EventRegistrations\Application\DTOs\UpdateRegistrationConfigDTO;
use PHPUnit\Framework\TestCase;

final class UpdateRegistrationConfigDTOTest extends TestCase
{
    public function test_from_array_provides_only_the_fields_it_received(): void
    {
        $dto = UpdateRegistrationConfigDTO::fromArray([
            'event_id' => 'event-1',
            'max_participants' => '5',
            'notification_email' => '',
        ]);

        $this->assertTrue($dto->provides('max_participants'));
        $this->assertTrue($dto->provides('notification_email'));
        $this->assertFalse($dto->provides('requires_payment'));
        $this->assertFalse($dto->provides('confirmation_message'));
        $this->assertFalse($dto->provides('custom_fields'));
        $this->assertFalse($dto->provides('event_id'));
        $this->assertSame(5, $dto->maxParticipants);
    }

    public function test_a_dto_built_directly_provides_every_field(): void
    {
        $dto = new UpdateRegistrationConfigDTO(eventId: 'event-1');

        foreach (UpdateRegistrationConfigDTO::FIELDS as $field) {
            $this->assertTrue($dto->provides($field), $field);
        }
    }

    public function test_unknown_keys_are_not_provided_fields(): void
    {
        $dto = UpdateRegistrationConfigDTO::fromArray(['event_id' => 'event-1', 'something_else' => true]);

        $this->assertFalse($dto->provides('something_else'));
    }
}
