<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional;

use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase as TYPO3FunctionalTestCase;

/**
 * Base test case for TYPO3 functional tests.
 *
 * Provides a full TYPO3 test instance with DI container, database,
 * and extension loading for testing component rendering.
 */
abstract class FunctionalTestCase extends TYPO3FunctionalTestCase
{
    /**
     * Load the fluid_primitives extension in the test instance.
     *
     * @var array<int, non-empty-string>
     */
    protected array $testExtensionsToLoad = [
        'jramke/fluid-primitives',
    ];

    /**
     * Core extensions required for component rendering.
     *
     * @var array<int, non-empty-string>
     */
    protected array $coreExtensionsToLoad = [
        'core',
        'backend',
        'frontend',
        'extbase',
        'fluid',
    ];

    /**
     * Use SQLite for functional tests (no external database required).
     *
     * @var array<string, mixed>
     */
    protected array $configurationToUseInTestInstance = [
        'DB' => [
            'Connections' => [
                'Default' => [
                    'driver' => 'pdo_sqlite',
                ],
            ],
        ],
    ];
}
