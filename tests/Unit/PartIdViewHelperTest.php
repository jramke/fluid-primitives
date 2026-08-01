<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit\ViewHelpers;

use Jramke\FluidPrimitives\Tests\TestCase;
use Jramke\FluidPrimitives\ViewHelpers\PartIdViewHelper;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContext;
use TYPO3Fluid\Fluid\Core\Variables\StandardVariableProvider;

final class PartIdViewHelperTest extends TestCase
{
    private RenderingContext $renderingContext;

    private StandardVariableProvider $variableProvider;

    private PartIdViewHelper $viewHelper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->renderingContext = new RenderingContext();
        $this->variableProvider = new StandardVariableProvider();

        $this->renderingContext->setVariableProvider($this->variableProvider);

        $this->viewHelper = new PartIdViewHelper();
        $this->viewHelper->setRenderingContext($this->renderingContext);
    }

    #[Test]
    public function generatesDeterministicIdForPartWithinRootComponent(): void
    {
        $this->setupRootComponent();

        $result = $this->render('root');

        $this->assertSame('collapsible:«f1»', $result);
    }

    #[Test]
    public function generatesIdWithValueForMultiInstancePart(): void
    {
        $this->setupRootComponent();

        $result = $this->render('trigger', 'tab-1');

        $this->assertSame('collapsible:«f1»:trigger:tab-1', $result);
    }

    #[Test]
    public function usesExplicitIdFromIdsConfiguration(): void
    {
        $this->setupRootComponent();

        $this->variableProvider->remove('context');
        $this->variableProvider->add('context', [
            'ids' => [
                'root' => 'my-custom-root-id',
            ],
        ]);

        $result = $this->render('root');

        $this->assertSame('my-custom-root-id', $result);
    }

    #[Test]
    public function fallsBackToGeneratedIdWhenExplicitIdIsEmpty(): void
    {
        $this->setupRootComponent();

        $this->variableProvider->remove('context');
        $this->variableProvider->add('context', [
            'ids' => [
                'root' => '',
            ],
        ]);

        $result = $this->render('root');

        $this->assertSame('collapsible:«f1»', $result);
    }

    #[Test]
    public function supportsDashedPartNames(): void
    {
        $this->setupRootComponent();

        $result = $this->render('item-trigger', 'item-1');

        $this->assertSame('collapsible:«f1»:item-trigger:item-1', $result);
    }

    #[Test]
    public function generatesIdUsingRootIdFromNestedComponentContext(): void
    {
        $this->variableProvider->add('component', [
            'fullName' => 'Accordion.Item',
        ]);

        $this->variableProvider->add('context', [
            'rootId' => '«f2»',
            'ids' => [],
        ]);

        $result = $this->render('item', 'my-item');

        $this->assertSame('accordion:«f2»:item:my-item', $result);
    }

    #[Test]
    public function throwsExceptionOutsideComponentContext(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('can only be used inside a component context');

        $this->render('root');
    }

    #[Test]
    public function throwsExceptionWhenRootIdIsMissing(): void
    {
        $this->variableProvider->add('component', [
            'fullName' => 'Collapsible.Root',
        ]);

        $this->variableProvider->add('context', [
            'ids' => [],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No rootId found');

        $this->render('root');
    }

    private function setupRootComponent(): void
    {
        $this->variableProvider->add('component', [
            'fullName' => 'Collapsible.Root',
        ]);

        $this->variableProvider->add('rootId', '«f1»');

        $this->variableProvider->add('context', [
            'ids' => [],
        ]);
    }

    private function render(string $part, ?string $value = null): string
    {
        $this->viewHelper->setArguments([
            'part' => $part,
            'value' => $value,
        ]);

        return $this->viewHelper->render();
    }
}
