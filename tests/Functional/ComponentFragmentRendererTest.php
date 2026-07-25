<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional;

use Jramke\FluidPrimitives\Service\ComponentFragmentRenderer;
use PHPUnit\Framework\Attributes\Test;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

final class ComponentFragmentRendererTest extends FunctionalTestCase
{
    #[Test]
    public function rendersAComponentInIsolationWithHydrationData(): void
    {
        // Triggers the base class' request/site/language bootstrapping so a
        // real ServerRequestInterface is available in $GLOBALS.
        $this->getView();
        $renderingContext = $this->getView()->getRenderingContext();
        self::assertInstanceOf(RenderingContextInterface::class, $renderingContext);

        $renderer = $this->get(ComponentFragmentRenderer::class);

        $result = $renderer->render(
            'primitives:collapsible.root',
            [
                'rootId' => 'fragment-test',
                'defaultOpen' => true,
            ],
            $renderingContext,
        );

        self::assertArrayHasKey('html', $result);
        self::assertArrayHasKey('hydrationData', $result);
        self::assertStringContainsString('data-scope="collapsible"', $result['html']);
        self::assertStringContainsString('data-state="open"', $result['html']);
        self::assertArrayHasKey('collapsible', $result['hydrationData']);
        self::assertArrayHasKey('fragment-test', $result['hydrationData']['collapsible']);
    }

    #[Test]
    public function onlyReturnsHydrationDataForTheRequestedFragmentNotLeftoverState(): void
    {
        $this->getView();
        $renderingContext = $this->getView()->getRenderingContext();
        self::assertInstanceOf(RenderingContextInterface::class, $renderingContext);

        $renderer = $this->get(ComponentFragmentRenderer::class);

        // Render once so the shared HydrationRegistry singleton is not empty...
        $renderer->render('primitives:collapsible.root', ['rootId' => 'first'], $renderingContext);

        // ...then render a second, unrelated fragment and make sure its
        // hydrationData does not still contain the first one.
        $result = $renderer->render('primitives:collapsible.root', ['rootId' => 'second'], $renderingContext);

        self::assertArrayNotHasKey('first', $result['hydrationData']['collapsible']);
        self::assertArrayHasKey('second', $result['hydrationData']['collapsible']);
    }

    #[Test]
    public function throwsForInvalidComponentNameFormat(): void
    {
        $this->getView();
        $renderingContext = $this->getView()->getRenderingContext();
        self::assertInstanceOf(RenderingContextInterface::class, $renderingContext);

        $renderer = $this->get(ComponentFragmentRenderer::class);

        $this->expectException(\RuntimeException::class);
        $renderer->render('collapsible-without-namespace', [], $renderingContext);
    }
}
