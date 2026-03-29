<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional;

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Core\View\ViewInterface;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase as TYPO3FunctionalTestCase;

/**
 * Base test case for TYPO3 functional tests.
 *
 * Provides a full TYPO3 test instance with DI container, database,
 * and extension loading for testing component rendering.
 */
abstract class FunctionalTestCase extends TYPO3FunctionalTestCase
{
    private ?ViewInterface $view = null;

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

    /**
     * Render a Fluid template with primitives components.
     *
     * @param string $template The Fluid template source
     * @param array<string, mixed> $variables Variables to assign to the view
     * @return string The rendered HTML
     */
    protected function renderTemplate(string $template, array $variables = []): string
    {
        $view = $this->getView();

        foreach ($variables as $key => $value) {
            $view->assign($key, $value);
        }

        $view->getRenderingContext()->getTemplatePaths()->setTemplateSource($template);

        return $view->render();
    }

    /**
     * Get or create the view instance for rendering primitives.
     */
    protected function getView(): ViewInterface
    {
        if ($this->view === null) {
            $this->view = $this->createPrimitivesView();
        }

        return $this->view;
    }

    /**
     * Create a Fluid view configured for rendering primitives components.
     */
    private function createPrimitivesView(): ViewInterface
    {
        // Set up a frontend request for proper context
        $request = new ServerRequest();
        $request = $request->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE)->withAttribute(
            'normalizedParams',
            NormalizedParams::createFromRequest($request),
        );
        $GLOBALS['TYPO3_REQUEST'] = $request;

        // Get the ViewFactory from the container
        $viewFactory = $this->get(ViewFactoryInterface::class);

        // Get the template paths from the extension
        $extensionPath = ExtensionManagementUtility::extPath('fluid_primitives');

        // Create a view using the factory
        $view = $viewFactory->create(
            new ViewFactoryData(templateRootPaths: [$extensionPath . 'Resources/Private/Primitives']),
        );

        // Register the primitives namespace
        $view
            ->getRenderingContext()
            ->getViewHelperResolver()
            ->addNamespace('primitives', new \Jramke\FluidPrimitives\Component\ComponentPrimitivesCollection());

        // Register the ui namespace
        $view
            ->getRenderingContext()
            ->getViewHelperResolver()
            ->addNamespace('ui', 'Jramke\\FluidPrimitives\\ViewHelpers');

        // Clear the hydration registry
        HydrationRegistry::getInstance()->clear();

        return $view;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_REQUEST']);
        $this->view = null;
        parent::tearDown();
    }
}
