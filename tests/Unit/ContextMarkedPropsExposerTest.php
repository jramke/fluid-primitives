<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Component\ComponentCollectionInterface;
use Jramke\FluidPrimitives\Factory\ComponentContextFactory;
use Jramke\FluidPrimitives\Service\Component\ContextMarkedPropsExposer;
use Jramke\FluidPrimitives\Service\ContextService;
use Jramke\FluidPrimitives\Tests\Helper\ConcreteTestContext;
use Jramke\FluidPrimitives\Tests\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContext;
use TYPO3Fluid\Fluid\Core\ViewHelper\ArgumentDefinition;

#[AllowMockObjectsWithoutExpectations]
final class ContextMarkedPropsExposerTest extends TestCase
{
    #[Test]
    public function restoresThePreviouslyExposedValueOnceTheReturnedClosureIsInvoked(): void
    {
        // Regression test: expose() used to permanently overwrite the shared root context with no
        // way back, so a value exposed by one part (e.g. select.item's own `item`) kept leaking
        // into every sibling rendered after it - not just that part's own descendants - until some
        // other part happened to overwrite the same key again. ComponentRenderer now invokes the
        // returned closure once this part's own render is fully done, restoring exactly what was
        // there before - this is what makes that scoping actually work.
        $renderingContext = new RenderingContext();
        $context = (new ComponentContextFactory())->create(
            ConcreteTestContext::class,
            $renderingContext,
            $renderingContext,
            $this->createMock(ComponentCollectionInterface::class),
        );
        ContextService::addToRenderingContext($renderingContext, 'select', $context);

        $exposer = new ContextMarkedPropsExposer();
        $argumentDefinitions = ['item' => new ArgumentDefinition('item', 'mixed', '', false, null)];

        $restoreAfterApple = $exposer->expose(
            ['item' => true],
            ['item' => 'apple'],
            $argumentDefinitions,
            $renderingContext,
            'select.item',
        );
        $this->assertSame(['item' => 'apple'], $context->get('item'));

        $restoreAfterApple();
        $this->assertNull($context->get('item'));

        $restoreAfterBanana = $exposer->expose(
            ['item' => true],
            ['item' => 'banana'],
            $argumentDefinitions,
            $renderingContext,
            'select.item',
        );
        $this->assertSame(['item' => 'banana'], $context->get('item'));

        $restoreAfterBanana();
        $this->assertNull($context->get('item'));
    }

    #[Test]
    public function returnsNullWhenNoContextIsActiveForTheComponent(): void
    {
        $exposer = new ContextMarkedPropsExposer();

        $result = $exposer->expose(
            ['item' => true],
            ['item' => 'apple'],
            ['item' => new ArgumentDefinition('item', 'mixed', '', false, null)],
            new RenderingContext(),
            'select.item',
        );

        $this->assertNull($result);
    }
}
