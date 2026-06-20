<?php

declare(strict_types=1);

use TYPO3\TestingFramework\Core\Testbase;

/**
 * Bootstrap for all tests (unit and functional).
 *
 * Handles both contexts:
 * - Running from package directory: loads package vendor autoloader
 * - Running from monorepo root: autoloader already loaded, skip
 *
 * Initializes TYPO3 testing framework for functional tests.
 */

// Only load autoloader if not already loaded (when running from root)
if (!class_exists(Testbase::class)) {
    require dirname(__DIR__) . '/vendor/autoload.php';
}

// Initialize TYPO3 testing framework for functional tests
(static function () {
    $testbase = new Testbase();
    $testbase->defineOriginalRootPath();
    $testbase->createDirectory(ORIGINAL_ROOT . 'typo3temp/var/tests');
    $testbase->createDirectory(ORIGINAL_ROOT . 'typo3temp/var/transient');
})();
