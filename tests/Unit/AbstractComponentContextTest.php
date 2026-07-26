<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Component\ComponentCollectionInterface;
use Jramke\FluidPrimitives\Factory\ComponentContextFactory;
use Jramke\FluidPrimitives\Tests\Helper\ConcreteTestContext;
use Jramke\FluidPrimitives\Tests\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

#[AllowMockObjectsWithoutExpectations]
final class AbstractComponentContextTest extends TestCase
{
    private RenderingContextInterface $renderingContext;
    private RenderingContextInterface $parentRenderingContext;
    private ComponentCollectionInterface $componentResolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->renderingContext = $this->createMock(RenderingContextInterface::class);
        $this->parentRenderingContext = $this->createMock(RenderingContextInterface::class);
        $this->componentResolver = $this->createMock(ComponentCollectionInterface::class);
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
}
