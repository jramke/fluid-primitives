<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional;

use Jramke\FluidPrimitives\Component\ComponentPrimitivesCollection;
use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Registry\NestedComponentRegistry;
use Jramke\FluidPrimitives\Registry\PortalRegistry;
use Jramke\FluidPrimitives\Registry\ReferencedRootRegistry;
use Jramke\FluidPrimitives\Registry\UsedRootIdRegistry;
use Jramke\FluidPrimitives\Tests\Helper\HtmlElement;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\TypoScript\AST\Node\RootNode;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Core\View\ViewInterface;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Extbase\Mvc\Request;
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

        // Each render stands for its own request, so a test may reuse a rootId across renders
        UsedRootIdRegistry::clear();
        $html = $view->render();
        $this->assertNoRepeatedAttributes($html);

        return $html;
    }

    /**
     * A tag that writes an attribute twice is invalid HTML, and the browser keeps only the first, which hides
     * a difference between the markup of the server and the one the client spreads.
     */
    protected function assertNoRepeatedAttributes(string $html): void
    {
        $repeated = [];
        foreach (HtmlElement::parseAll($html) as $element) {
            if ($element->repeatedAttributes !== []) {
                $repeated[] = sprintf(
                    '<%s %s> repeats %s',
                    $element->tag,
                    implode(' ', array_keys($element->attributes)),
                    implode(', ', $element->repeatedAttributes),
                );
            }
        }

        $this->assertSame([], $repeated, 'A rendered tag repeats an attribute.');
    }

    /**
     * Get or create the view instance for rendering primitives.
     */
    protected function getView(): ViewInterface
    {
        if (!$this->view instanceof ViewInterface) {
            $this->view = $this->createPrimitivesView();
        }

        return $this->view;
    }

    /**
     * The elements of `$html` that carry `$attribute` (`data-accordion-item-trigger`), in document order.
     *
     * @return list<HtmlElement>
     */
    protected function elementsWith(string $html, string $attribute): array
    {
        return array_values(array_filter(
            HtmlElement::parseAll($html),
            static fn(HtmlElement $element): bool => $element->has($attribute),
        ));
    }

    /**
     * The value of `$attribute` on every element of `$html` that carries it, in document order.
     *
     * @return list<string>
     */
    protected function valuesOf(string $html, string $attribute): array
    {
        return array_map(
            static fn(HtmlElement $element): string => $element->get($attribute) ?? '',
            $this->elementsWith($html, $attribute),
        );
    }

    /**
     * The elements of `$html` that carry `$attribute`, by their `data-value`: the parts rendered once per item.
     *
     * @return array<string, HtmlElement>
     */
    protected function elementsByValue(string $html, string $attribute): array
    {
        $elements = [];
        foreach ($this->elementsWith($html, $attribute) as $element) {
            $elements[$element->get('data-value') ?? ''] = $element;
        }

        return $elements;
    }

    /**
     * The one element of `$html` that carries `$attribute`.
     */
    protected function elementWith(string $html, string $attribute): HtmlElement
    {
        $elements = $this->elementsWith($html, $attribute);
        $this->assertCount(1, $elements, sprintf('Expected exactly one element with %s.', $attribute));

        return $elements[0];
    }

    protected function setRequestLocale(string $locale): void
    {
        $baseUri = new Uri('https://example.com/');
        $language = new SiteLanguage(0, $locale, $baseUri, ['title' => $locale]);
        $site = new Site('test', 1, [
            'base' => (string)$baseUri,
            'languages' => [],
            'errorHandling' => [],
            'routes' => [],
        ]);

        $request = $GLOBALS['TYPO3_REQUEST'] ?? new ServerRequest();
        if (!$request instanceof ServerRequest) {
            $request = new ServerRequest();
        }

        $request = $request->withAttribute('language', $language)->withAttribute('site', $site);
        $GLOBALS['TYPO3_REQUEST'] = $request;

        if ($this->view instanceof ViewInterface) {
            $this->view->getRenderingContext()->setAttribute(ServerRequest::class, $request);
            $this->view->getRenderingContext()->setAttribute(ServerRequestInterface::class, $request);
        }
    }

    /**
     * Makes the request of the view the Extbase request of a plugin, like the dispatch of a plugin has it.
     */
    protected function setExtbasePlugin(string $extensionName, string $pluginName): void
    {
        // creates the request with the site and the application type first
        $view = $this->getView();
        $request = $GLOBALS['TYPO3_REQUEST'] ?? new ServerRequest();
        if (!$request instanceof ServerRequest) {
            $request = new ServerRequest();
        }

        $parameters = new ExtbaseRequestParameters();
        $parameters->setControllerExtensionName($extensionName);
        $parameters->setPluginName($pluginName);
        // what ExtensionService and the doctype read from, empty here
        $typoScript = new FrontendTypoScript(new RootNode(), [], [], []);
        $typoScript->setSetupArray([]);
        $typoScript->setConfigArray([]);
        $request = new Request($request->withAttribute('extbase', $parameters)->withAttribute(
            'frontend.typoscript',
            $typoScript,
        ));
        $GLOBALS['TYPO3_REQUEST'] = $request;

        $this->get(ConfigurationManagerInterface::class)->setRequest($request);
        $view->getRenderingContext()->setAttribute(ServerRequestInterface::class, $request);
    }

    /**
     * Create a Fluid view configured for rendering primitives components.
     */
    private function createPrimitivesView(): ViewInterface
    {
        // Set up a frontend request for proper context, but keep any locale/site overrides set by tests.
        $request = $GLOBALS['TYPO3_REQUEST'] ?? new ServerRequest();
        if (!$request instanceof ServerRequest) {
            $request = new ServerRequest();
        }

        if (!$request->getAttribute('site')) {
            $baseUri = new Uri('https://example.com/');
            $site = new Site('test', 1, [
                'base' => (string)$baseUri,
                'languages' => [],
                'errorHandling' => [],
                'routes' => [],
            ]);
            $request = $request->withAttribute('site', $site);
        }

        if (!$request->getAttribute('language')) {
            $baseUri = new Uri('https://example.com/');
            $language = new SiteLanguage(0, 'en_US', $baseUri, ['title' => 'English']);
            $request = $request->withAttribute('language', $language);
        }

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

        $view->getRenderingContext()->setAttribute(ServerRequest::class, $request);
        $view->getRenderingContext()->setAttribute(ServerRequestInterface::class, $request);

        // Register the primitives namespace
        $view
            ->getRenderingContext()
            ->getViewHelperResolver()
            ->addNamespace('primitives', new ComponentPrimitivesCollection());

        // Register the ui namespace
        $view
            ->getRenderingContext()
            ->getViewHelperResolver()
            ->addNamespace('ui', 'Jramke\\FluidPrimitives\\ViewHelpers');

        // The registries outlive a test, so each one starts from nothing
        HydrationRegistry::getInstance()->clear();
        NestedComponentRegistry::getInstance()->clear();
        ReferencedRootRegistry::clear();
        PortalRegistry::getInstance()->clearAll();

        return $view;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_REQUEST']);
        $this->view = null;
        parent::tearDown();
    }
}
