<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Hydration;

use Jramke\FluidPrimitives\Contexts\GlobalContext;
use Jramke\FluidPrimitives\Contexts\GlobalContextInterface;
use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Tests\Fixtures\GlobalContext\ProjectGlobalContext;
use Jramke\FluidPrimitives\Tests\Fixtures\GlobalContextComponentCollection;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Page\AssetCollector;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The global context end to end: a project style one handed out instead of the library default, read
 * from templates of a collection and written once into the inline script.
 */
final class GlobalContextTest extends FunctionalTestCase
{
    private const string HOSTILE = '</script><b>"quoted" \'single\' & ampersand';

    private ProjectGlobalContext $globals;

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['globalFixture'] = [
            GlobalContextComponentCollection::class,
        ];
        $this
            ->getView()
            ->getRenderingContext()
            ->getViewHelperResolver()
            ->addNamespace('globalFixture', new GlobalContextComponentCollection());

        // what a project's Services.yaml alias does for the container
        $this->globals = new ProjectGlobalContext(self::HOSTILE);
        GeneralUtility::setSingletonInstance(GlobalContextInterface::class, $this->globals);
        (new \ReflectionProperty(HydrationRegistry::class, 'instance'))->setValue(
            null,
            new HydrationRegistry($this->get(AssetCollector::class), $this->globals),
        );
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['globalFixture']);
        (new \ReflectionProperty(HydrationRegistry::class, 'instance'))->setValue(null, null);
        GeneralUtility::removeSingletonInstance(GlobalContextInterface::class, $this->globals);
        parent::tearDown();
    }

    private function inlineScript(): string
    {
        $scripts = $this->get(AssetCollector::class)->getInlineJavaScripts();

        return (string)($scripts['fluid-primitives-hydration-data']['source'] ?? '');
    }

    #[Test]
    public function readsAGlobalFromATemplateAndEmitsTheScriptWithoutAnyHydratingComponent(): void
    {
        $html = $this->renderTemplate('<globalFixture:reader />');

        $this->assertStringContainsString(
            htmlspecialchars(self::HOSTILE) . '||#f00|&lt;svg/&gt;',
            $html,
            'server only token and escaped value',
        );
        $script = $this->inlineScript();
        $this->assertStringContainsString('window.FluidPrimitives', $script);
        $this->assertStringContainsString('"lightbox":{"icons":{"close":"\\u003Csvg/\\u003E"}}', $script);
        $this->assertStringNotContainsString('designTokens', $script, 'a server only getter stays off the client');
        $this->assertSame(1, substr_count($script, 'window.FluidPrimitives='));
    }

    #[Test]
    public function neverLetsAStringBreakOutOfTheInlineScript(): void
    {
        $this->renderTemplate('<globalFixture:reader />');

        $script = $this->inlineScript();
        $this->assertStringNotContainsString('</script>', $script);
        $this->assertStringNotContainsString('<b>', $script);
        $this->assertStringContainsString('\\u003C/script\\u003E', $script);
    }

    #[Test]
    public function writesTheGlobalsOnceAndKeepsThemOutOfThePerInstanceProps(): void
    {
        $this->renderTemplate('<globalFixture:hydrated rootId="h1" /><globalFixture:hydrated rootId="h2" />');

        $script = $this->inlineScript();
        $this->assertSame(1, substr_count($script, '"siteName"'), 'once, not per instance');

        $registry = HydrationRegistry::getInstance();
        foreach (['h1', 'h2'] as $id) {
            $props = $registry->get('globalFixture', 'hydrated', $id)['props'];
            $this->assertSame(['id', 'ids'], array_keys($props));
        }
    }

    #[Test]
    public function emitsNoScriptOnAPageWhereNothingHydratesAndNothingReadsAGlobal(): void
    {
        $this->renderTemplate('<globalFixture:silent>plain</globalFixture:silent>');

        $this->assertSame('', $this->inlineScript());
    }

    #[Test]
    public function readsGlobalsInAPartRenderedOnItsOwn(): void
    {
        // Open item 3 of FP-003: a part outside its root has no `context` variable at all, so the
        // accessor resolves to nothing and renders empty instead of throwing.
        $html = $this->renderTemplate('<globalFixture:reader.part />');

        $this->assertStringContainsString('<i data-part></i>', $html);
    }

    #[Test]
    public function readsGlobalsInAPartRenderedInsideItsRoot(): void
    {
        $html = $this->renderTemplate('<globalFixture:reader><globalFixture:reader.part /></globalFixture:reader>');

        $this->assertStringContainsString('<i data-part>' . htmlspecialchars(self::HOSTILE) . '</i>', $html);
    }

    #[Test]
    public function theContainerAliasesTheInterfaceToTheLibraryDefaultUntilAProjectOverridesIt(): void
    {
        $this->assertInstanceOf(GlobalContext::class, $this->get(GlobalContextInterface::class));
    }
}
