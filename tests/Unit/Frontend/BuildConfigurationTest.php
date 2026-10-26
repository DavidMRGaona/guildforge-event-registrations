<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Unit\Frontend;

use PHPUnit\Framework\TestCase;

/**
 * Source guard: the module builds with the core's configuration, which keeps the core's public
 * frontend API out of the bundle, so the page loads one instance of each shared file.
 */
final class BuildConfigurationTest extends TestCase
{
    public function test_the_build_uses_the_core_configuration(): void
    {
        $config = file_get_contents(__DIR__.'/../../../vite.config.ts');
        $this->assertNotFalse($config);

        $this->assertStringContainsString("import { defineModuleConfig } from '../../vite.module.config.ts';", $config);
        $this->assertStringContainsString("defineModuleConfig({ name: 'event-registrations', root: import.meta.dirname })", $config);
    }

    public function test_the_module_declares_no_build_toolchain_of_its_own(): void
    {
        $this->assertFileDoesNotExist(__DIR__.'/../../../package.json');
    }
}
