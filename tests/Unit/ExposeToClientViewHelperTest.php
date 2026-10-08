<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Constants;
use Jramke\FluidPrimitives\Domain\Dto\ComponentIdentity;
use Jramke\FluidPrimitives\Tests\TestCase;
use Jramke\FluidPrimitives\ViewHelpers\ExposeToClientViewHelper;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContext;
use TYPO3Fluid\Fluid\Core\Variables\StandardVariableProvider;

final class ExposeToClientViewHelperTest extends TestCase
{
    private RenderingContext $renderingContext;
    private StandardVariableProvider $variableProvider;
    private ExposeToClientViewHelper $viewHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderingContext = new RenderingContext();
        $this->variableProvider = new StandardVariableProvider();
        $this->renderingContext->setVariableProvider($this->variableProvider);

        $this->viewHelper = new ExposeToClientViewHelper();
        $this->viewHelper->setRenderingContext($this->renderingContext);
    }

    #[Test]
    public function rendersTheMarkerInsideARootComponent(): void
    {
        $this->markAsComponent('accordion.root', isDeclaredRoot: true, isRenderedAsRoot: true);

        $this->assertSame(Constants::MANUALLY_EXPOSED_TO_CLIENT_MARKER, $this->viewHelper->render());
    }

    #[Test]
    public function allowsAComponentThatsRootOnlyViaTheFolderShapeDefault(): void
    {
        // Regression test: 'checkboxGroup.examples.selectAll' is root only because its own folder
        // has no Root.fluid.html sibling (the folder-shape default) - AbstractComponentCollection's
        // compile-time check already knows this
        // ({@see \Jramke\FluidPrimitives\Domain\Dto\ComponentIdentity::$isDeclaredRoot}), so
        // ui:exposeToClient works directly inside such a template without needing a PHP Context
        // class at all.
        $this->markAsComponent('checkboxGroup.examples.selectAll', isDeclaredRoot: true, isRenderedAsRoot: true);

        $this->assertSame(Constants::MANUALLY_EXPOSED_TO_CLIENT_MARKER, $this->viewHelper->render());
    }

    #[Test]
    public function noOpsWhenDeclaredRootDelegatesViaSpreadProps(): void
    {
        // Declared root (its own template shape allows ui:exposeToClient), but this specific call
        // is a spreadProps delegate (e.g. primitives:tooltip.root rendered through
        // ui:tooltip.root) - whichever ancestor it's rendered as root through is responsible for
        // its own hydration bookkeeping, so this call is a silent no-op rather than emitting a
        // marker nothing will ever collect.
        $this->markAsComponent('primitives.tooltip.root', isDeclaredRoot: true, isRenderedAsRoot: false);

        $this->assertSame('', $this->viewHelper->render());
    }

    #[Test]
    public function throwsOutsideARootComponent(): void
    {
        $this->markAsComponent('accordion.item', isDeclaredRoot: false, isRenderedAsRoot: false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('can only be used in a root component');

        $this->viewHelper->render();
    }

    #[Test]
    public function throwsOutsideAnyComponent(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('can only be used inside a component');

        $this->viewHelper->render();
    }

    /**
     * Sets up both channels a real render would: the public `component` Fluid variable (just
     * `fullName` here - all `ComponentUtility::isComponent()` needs) and the internal
     * `ComponentIdentity` on the `ViewHelperVariableContainer`
     * ({@see \Jramke\FluidPrimitives\Utility\ComponentRootUtility} reads it from there, not from
     * `component`).
     */
    private function markAsComponent(string $fullName, bool $isDeclaredRoot, bool $isRenderedAsRoot): void
    {
        $this->variableProvider->add('component', ['fullName' => $fullName]);
        $this->renderingContext->getViewHelperVariableContainer()->add(
            ComponentIdentity::class,
            ComponentIdentity::VHVC_KEY,
            new ComponentIdentity($isRenderedAsRoot, $isDeclaredRoot, null, '', '', null),
        );
    }
}
