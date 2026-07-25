<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional;

use Jramke\FluidPrimitives\Service\ComponentFragmentRenderer;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;

final class ComponentFragmentRendererTest extends FunctionalTestCase
{
    #[Test]
    public function rendersAComponentInIsolationWithHydrationData(): void
    {
        // Triggers the base class' request/site/language bootstrapping so a
        // real ServerRequestInterface is available in $GLOBALS.
        $this->getView();
        $request = $GLOBALS['TYPO3_REQUEST'];
        self::assertInstanceOf(ServerRequestInterface::class, $request);

        $renderer = $this->get(ComponentFragmentRenderer::class);

        $result = $renderer->render(
            'primitives:collapsible.root',
            [
                'rootId' => 'fragment-test',
                'defaultOpen' => true,
            ],
            $request,
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
        $request = $GLOBALS['TYPO3_REQUEST'];
        self::assertInstanceOf(ServerRequestInterface::class, $request);

        $renderer = $this->get(ComponentFragmentRenderer::class);

        // Render once so the shared HydrationRegistry singleton is not empty...
        $renderer->render('primitives:collapsible.root', ['rootId' => 'first'], $request);

        // ...then render a second, unrelated fragment and make sure its
        // hydrationData does not still contain the first one.
        $result = $renderer->render('primitives:collapsible.root', ['rootId' => 'second'], $request);

        self::assertArrayNotHasKey('first', $result['hydrationData']['collapsible']);
        self::assertArrayHasKey('second', $result['hydrationData']['collapsible']);
    }

    #[Test]
    public function throwsForInvalidComponentNameFormat(): void
    {
        $this->getView();
        $request = $GLOBALS['TYPO3_REQUEST'];
        self::assertInstanceOf(ServerRequestInterface::class, $request);

        $renderer = $this->get(ComponentFragmentRenderer::class);

        $this->expectException(\RuntimeException::class);
        $renderer->render('collapsible-without-namespace', [], $request);
    }
}
