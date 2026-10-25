<?php

declare(strict_types=1);

/*
 * The module autoloader only maps src/. Map the test namespace too, so tests can share
 * helpers (classes under tests/Support) that PHPUnit does not load as test files.
 */
$loader = require __DIR__.'/../../../vendor/autoload.php';
$loader->addPsr4('Modules\\EventRegistrations\\Tests\\', __DIR__);
