<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Feature;

use Tests\Support\Modules\ModuleTestCase;

/**
 * The module's help guides pass the knowledge base lint, so CI blocks a broken one.
 */
final class KnowledgeGuidesTest extends ModuleTestCase
{
    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    public function test_guides_are_valid(): void
    {
        $this->assertModuleKnowledgeGuidesAreValid('event-registrations');
    }
}
