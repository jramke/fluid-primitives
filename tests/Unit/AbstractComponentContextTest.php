<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Component\ComponentCollectionInterface;
use Jramke\FluidPrimitives\Contexts\AbstractComponentContext;
use Jramke\FluidPrimitives\Contexts\AbstractGlobalContext;
use Jramke\FluidPrimitives\Contexts\GlobalContextInterface;
use Jramke\FluidPrimitives\Factory\ComponentContextFactory;
use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Tests\Helper\ConcreteTestContext;
use Jramke\FluidPrimitives\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Page\AssetCollector;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

final class AbstractComponentContextTest extends TestCase
{
    private RenderingContextInterface $renderingContext;
    private RenderingContextInterface $parentRenderingContext;
    private ComponentCollectionInterface $componentResolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->renderingContext = $this->createStub(RenderingContextInterface::class);
        $this->parentRenderingContext = $this->createStub(RenderingContextInterface::class);
        $this->componentResolver = $this->createStub(ComponentCollectionInterface::class);
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(HydrationRegistry::class, 'instance'))->setValue(null, null);
        parent::tearDown();
    }

    /**
     * What the container hands out in production: the registry a first getGlobals() asks to emit the globals.
     */
    private function useHydrationRegistry(AbstractGlobalContext $globals): HydrationRegistry
    {
        $registry = new HydrationRegistry($this->createStub(AssetCollector::class), $globals);
        (new \ReflectionProperty(HydrationRegistry::class, 'instance'))->setValue(null, $registry);

        return $registry;
    }

    private function createContext(array $contextVariables = []): ConcreteTestContext
    {
        $factory = new ComponentContextFactory();

        return $factory->create(
            ConcreteTestContext::class,
            $this->renderingContext,
            $this->parentRenderingContext,
            $this->componentResolver,
            $contextVariables,
        );
    }

    #[Test]
    public function supportsNestedPropertyAccessViaDotNotation(): void
    {
        $context = $this->createContext([
            'scrollbar' => [
                'orientation' => 'vertical',
                'size' => 'small',
            ],
            'nested' => [
                'level1' => [
                    'level2' => 'deep-value',
                ],
            ],
        ]);

        $this->assertSame('vertical', $context->get('scrollbar.orientation'));
        $this->assertSame('small', $context->get('scrollbar.size'));
        $this->assertSame('deep-value', $context->get('nested.level1.level2'));
    }

    #[Test]
    public function returnsNullForNonExistentNestedPaths(): void
    {
        $context = $this->createContext([
            'scrollbar' => [
                'orientation' => 'vertical',
            ],
        ]);

        $this->assertNull($context->get('scrollbar.nonexistent'));
        $this->assertNull($context->get('nonexistent.path'));
        $this->assertNull($context->get('scrollbar.orientation.deeper'));
    }

    #[Test]
    public function prefersDirectKeyLookupOverDotNotationParsing(): void
    {
        $context = $this->createContext([
            'my.dotted.key' => 'direct-value',
            'my' => [
                'dotted' => [
                    'key' => 'nested-value',
                ],
            ],
        ]);

        $this->assertSame('direct-value', $context->get('my.dotted.key'));
    }

    #[Test]
    public function supportsArrayStyleAccessForContextVariables(): void
    {
        $context = $this->createContext([
            'orientation' => 'horizontal',
            'nested' => ['value' => 'test'],
        ]);

        $this->assertSame('horizontal', $context['orientation']);
        $this->assertSame('test', $context['nested.value']);
        $this->assertTrue(isset($context['orientation']));
        $this->assertFalse(isset($context['nonexistent']));
    }

    #[Test]
    public function handsEveryContextTheSharedGlobalContextInitializedWithTheRequest(): void
    {
        $request = new ServerRequest();
        $this->renderingContext = $this->createStub(RenderingContextInterface::class);
        $this->renderingContext->method('hasAttribute')->willReturn(true);
        $this->renderingContext->method('getAttribute')->willReturn($request);
        $globals = new class extends AbstractGlobalContext {};
        GeneralUtility::addInstance(GlobalContextInterface::class, $globals);
        $this->useHydrationRegistry($globals);
        $context = $this->createContext(['globals' => 'a variable named like it must not win']);

        $this->assertSame($globals, $context->getGlobals());
        $this->assertSame($globals, $context->getGlobals());
        $this->assertSame($request, $globals->getRequest());
        $this->assertSame($globals, $context['globals']);
    }

    #[Test]
    public function toleratesAnIsolatedRenderWithoutARequest(): void
    {
        $globals = new class extends AbstractGlobalContext {};
        GeneralUtility::addInstance(GlobalContextInterface::class, $globals);
        $this->useHydrationRegistry($globals);

        $this->assertSame($globals, $this->createContext()->getGlobals());
        $this->assertNull($globals->getRequest());
    }

    #[Test]
    public function doesNotAllowASubclassToDeclareItsOwnGetGlobals(): void
    {
        $this->assertTrue((new \ReflectionMethod(AbstractComponentContext::class, 'getGlobals'))->isFinal());
    }

    #[Test]
    public function keepsGetAndHasToItsOwnVariables(): void
    {
        $context = $this->createContext(['locale' => 'own']);

        $this->assertSame('own', $context->get('locale'));
        $this->assertNull($context->get('globals'));
        $this->assertFalse($context->has('globals'));
    }
}
