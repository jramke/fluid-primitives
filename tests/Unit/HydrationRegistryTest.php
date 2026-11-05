<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Contexts\GlobalContext;
use Jramke\FluidPrimitives\Domain\Dto\HydrationEntry;
use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Registry\NestedComponentRegistry;
use Jramke\FluidPrimitives\Service\TranslatorService;
use Jramke\FluidPrimitives\Tests\Fixtures\GlobalContext\ProjectGlobalContext;
use Jramke\FluidPrimitives\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Page\AssetCollector;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class HydrationRegistryTest extends TestCase
{
    private ?string $capturedJs = null;
    private ?array $capturedAttributes = null;
    private ?array $capturedOptions = null;
    private AssetCollector $assetCollector;
    private HydrationRegistry $registry;
    private GlobalContext $globalContext;

    protected function setUp(): void
    {
        parent::setUp();
        $this->capturedJs = null;
        $this->capturedAttributes = null;

        $this->assetCollector = $this->createStub(AssetCollector::class);
        $this->assetCollector
            ->method('addInlineJavaScript')
            ->willReturnCallback(function ($id, $js, $attributes, $options) {
                $this->capturedJs = $js;
                $this->capturedAttributes = $attributes;
                $this->capturedOptions = $options;
                return $this->assetCollector;
            });

        $this->globalContext = new GlobalContext();
        $this->registry = new HydrationRegistry($this->assetCollector, $this->globalContext);
    }

    #[Test]
    public function writesTheDataAsAJsonBlockTheClientFindsByItsAttribute(): void
    {
        $this->registry->add('ui', 'dialog', new HydrationEntry('«f1»', [], true, []));

        $this->assertSame('application/json', $this->capturedAttributes['type'] ?? null);
        $this->assertArrayHasKey('data-fluid-primitives-hydration-data', $this->capturedAttributes);
        $this->assertSame(['priority' => true], $this->capturedOptions);
    }

    #[Test]
    public function keepsEveryInstanceOfEveryComponentTypeOfANamespace(): void
    {
        $this->registry->add('ui', 'accordion', new HydrationEntry('«f1»', [], true, ['type' => 'first-accordion']));
        $this->registry->add('ui', 'accordion', new HydrationEntry('«f2»', [], true, ['type' => 'second-accordion']));
        $this->registry->add('ui', 'dialog', new HydrationEntry('«f3»', [], true, ['type' => 'dialog']));

        $all = $this->registry->getAll();

        $this->assertSame('first-accordion', $all['ui']['accordion']['«f1»']['props']['type']);
        $this->assertSame('second-accordion', $all['ui']['accordion']['«f2»']['props']['type']);
        $this->assertSame('dialog', $all['ui']['dialog']['«f3»']['props']['type']);
    }

    #[Test]
    public function keepsSameNamedComponentsFromDifferentNamespacesSeparate(): void
    {
        $this->registry->add('ui', 'select', new HydrationEntry('«f1»', [], true, ['type' => 'ui-select']));
        $this->registry->add('primitives', 'select', new HydrationEntry('«f2»', [], true, [
            'type' => 'primitives-select',
        ]));

        $all = $this->registry->getAll();

        $this->assertSame('ui-select', $all['ui']['select']['«f1»']['props']['type']);
        $this->assertSame('primitives-select', $all['primitives']['select']['«f2»']['props']['type']);
    }

    #[Test]
    public function nestsMultiSegmentComponentTypesByDottedPathSegment(): void
    {
        // A dot-joined componentType (e.g. a tiered component's own identity, see
        // ComponentNameUtility::getComponentBaseNameFromViewHelperName()) builds one nested array
        // level per segment, rather than being stored under one flat "molecules.checkboxGroup" key -
        // this is what the client's own getHydrationData() walks to find it.
        $this->registry->add('ui', 'molecules.checkboxGroup', new HydrationEntry('«f1»', [], true, [
            'type' => 'checkbox-group',
        ]));
        $this->registry->add('ui', 'molecules.tooltip', new HydrationEntry('«f2»', [], true, ['type' => 'tooltip']));

        $all = $this->registry->getAll();

        $this->assertSame('checkbox-group', $all['ui']['molecules']['checkboxGroup']['«f1»']['props']['type']);
        $this->assertSame('tooltip', $all['ui']['molecules']['tooltip']['«f2»']['props']['type']);
        $this->assertSame(
            'checkbox-group',
            $this->registry->get('ui', 'molecules.checkboxGroup', '«f1»')['props']['type'],
        );
    }

    #[Test]
    public function exposesTheLanguageOfTheRequestAsALocaleTheClientCanUseWithIntl(): void
    {
        // What the docs pass on as `getGlobal('locale')` to `Intl`, which wants a BCP 47 tag, not `de_DE`.
        $language = new SiteLanguage(0, 'de_DE', new Uri('https://example.com/'), []);
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest())->withAttribute('language', $language);
        GeneralUtility::addInstance(
            TranslatorService::class,
            new TranslatorService($this->createStub(LanguageServiceFactory::class)),
        );

        $globals = $this->registry->getGlobals();

        $this->assertSame('de-DE', $globals['locale']);
        // no Environment is initialized in a unit test, which counts as production
        $this->assertFalse($globals['debug']);
    }

    #[Test]
    public function exposesNoLocaleWithoutARequest(): void
    {
        unset($GLOBALS['TYPO3_REQUEST']);

        $this->assertArrayNotHasKey('locale', $this->registry->getGlobals());
    }

    #[Test]
    public function addsInlineJavaScriptWithComponentData(): void
    {
        $this->registry->add('ui', 'accordion', new HydrationEntry('«f1»', [], true, ['multiple' => true]));

        $this->assertStringContainsString('"accordion"', $this->capturedJs);
        $this->assertStringContainsString('"«f1»"', $this->capturedJs);
        $this->assertStringContainsString('"multiple":true', $this->capturedJs);
    }

    #[Test]
    public function includesNestedComponentsFromTheInjectedSiblingRegistryInTheInlineScript(): void
    {
        // Constructed directly (not via ::getInstance(), which needs a DI container this unit test
        // doesn't bootstrap) and wired in through the same constructor param HydrationRegistry's own
        // DI-resolved instance would receive in production - proving the two registries are meant
        // to be the *same* object, not proving anything about the container wiring itself.
        $nestedComponentRegistry = new NestedComponentRegistry();
        $nestedComponentRegistry->pushTrackingScope('field-array:«f0»:itemTemplate');
        $nestedComponentRegistry->recordNestedComponent('field', '«f1»');
        $nestedComponentRegistry->popTrackingScope();

        $registry = new HydrationRegistry(
            $this->assetCollector,
            $this->globalContext,
            nestedComponentRegistry: $nestedComponentRegistry,
        );
        $registry->add('primitives', 'field', new HydrationEntry('«f1»', [], true, ['name' => 'firstName']));

        $this->assertStringContainsString('nestedComponents', $this->capturedJs);
        $this->assertStringContainsString('field-array:«f0»:itemTemplate', $this->capturedJs);
    }

    #[Test]
    public function takesTheGlobalsFromWhateverTheGlobalContextExposes(): void
    {
        $registry = new HydrationRegistry($this->assetCollector, new ProjectGlobalContext());

        $this->assertSame(
            ['siteName' => 'Demo', 'lightbox' => ['icons' => ['close' => '<svg/>']], 'plain' => true],
            $registry->getGlobals(),
        );
    }

    #[Test]
    public function emitsTheScriptWhenOnlyAGlobalWasRead(): void
    {
        $registry = new HydrationRegistry($this->assetCollector, new ProjectGlobalContext());

        $registry->requestGlobals();

        $this->assertStringContainsString('"siteName"', (string)$this->capturedJs);
        $this->assertStringContainsString('"close":"\u003Csvg/\u003E"', (string)$this->capturedJs);
    }

    #[Test]
    public function emitsNoScriptWhenNeitherAComponentNorAGlobalWasUsed(): void
    {
        $registry = new HydrationRegistry($this->assetCollector, new ProjectGlobalContext());

        $registry->getGlobals();

        $this->assertNull($this->capturedJs);
    }

    #[Test]
    public function encodesEveryEmptyMapAsAnObject(): void
    {
        $registry = new HydrationRegistry($this->assetCollector, new GlobalContext());
        unset($GLOBALS['TYPO3_REQUEST']);

        $registry->requestGlobals();

        $this->assertMatchesRegularExpression(
            '/"globals":\{"debug":false\},"hydrationData":\{\},"nestedComponents":\{\}/',
            (string)$this->capturedJs,
        );
    }

    #[Test]
    public function addsABlockForTheUncachedContentNextToTheOneOfTheCachedContentWhichKeepsTheGlobals(): void
    {
        $assetCollector = new AssetCollector();

        // the request that renders the cached content: its block goes into the page cache
        $cachedContent = new HydrationRegistry($assetCollector, new GlobalContext());
        $cachedContent->add('ui', 'toaster', new HydrationEntry('toaster', [], true, []));
        $cachedContent->add('ui', 'button', new HydrationEntry('«f1»', [], true, []));

        // a later request gets the page from the cache, with that block, and renders only the uncached content
        $uncachedContent = new HydrationRegistry($assetCollector, new GlobalContext());
        $uncachedContent->requestGlobals();
        $this->assertCount(1, $assetCollector->getInlineJavaScripts(), 'a global read alone adds no block');
        $uncachedContent->add('ui', 'flashMessages', new HydrationEntry('«f2»', [], true, []));
        $uncachedContent->add('ui', 'flashMessages', new HydrationEntry('«f3»', [], true, []));

        $scripts = $assetCollector->getInlineJavaScripts();
        $this->assertSame(
            ['fluid-primitives-hydration-data', 'fluid-primitives-hydration-data-2'],
            array_keys($scripts),
        );
        $cached = json_decode($scripts['fluid-primitives-hydration-data']['source'], true);
        $uncached = json_decode($scripts['fluid-primitives-hydration-data-2']['source'], true);
        $this->assertEqualsCanonicalizing(['button', 'toaster'], array_keys($cached['hydrationData']['ui']));
        $this->assertSame(['flashMessages'], array_keys($uncached['hydrationData']['ui']));
        $this->assertSame(['«f2»', '«f3»'], array_keys($uncached['hydrationData']['ui']['flashMessages']));
        $this->assertArrayHasKey('globals', $cached);
        $this->assertArrayNotHasKey('globals', $uncached);
    }

    #[Test]
    public function forgetsTheRequestedGlobalsOnClear(): void
    {
        $registry = new HydrationRegistry($this->assetCollector, new ProjectGlobalContext());
        $registry->requestGlobals();
        $this->capturedJs = null;

        $registry->clear();
        $registry->getGlobals();

        $this->assertNull($this->capturedJs);
    }
}
