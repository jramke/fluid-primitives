<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Tests\TestCase;
use Jramke\FluidPrimitives\ViewHelpers\RefViewHelper;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContext;
use TYPO3Fluid\Fluid\Core\Variables\StandardVariableProvider;

final class RefViewHelperTest extends TestCase
{
    private RenderingContext $renderingContext;
    private StandardVariableProvider $variableProvider;
    private RefViewHelper $viewHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderingContext = new RenderingContext();
        $this->variableProvider = new StandardVariableProvider();
        $this->renderingContext->setVariableProvider($this->variableProvider);

        $this->viewHelper = new RefViewHelper();
        $this->viewHelper->setRenderingContext($this->renderingContext);
    }

    #[Test]
    public function rendersDataAttributesForARef(): void
    {
        $this->variableProvider->add('component', ['fullName' => 'Collapsible.Root']);
        $this->variableProvider->add('rootId', '«f1»');

        $this->viewHelper->setArguments([
            'name' => 'trigger',
            'asArray' => false,
            'data' => [],
        ]);

        $result = $this->viewHelper->render();

        $this->assertStringContainsString('data-scope="collapsible"', $result);
        $this->assertStringContainsString('data-part="trigger"', $result);
        $this->assertStringContainsString('data-hydrate-collapsible="«f1»"', $result);
    }

    #[Test]
    public function rendersRootRefCorrectly(): void
    {
        $this->variableProvider->add('component', ['fullName' => 'Collapsible.Root']);
        $this->variableProvider->add('rootId', '«f1»');

        $this->viewHelper->setArguments([
            'name' => 'root',
            'asArray' => false,
            'data' => [],
        ]);

        $result = $this->viewHelper->render();

        $this->assertStringContainsString('data-part="root"', $result);
        $this->assertStringContainsString('data-hydrate-collapsible="«f1»"', $result);
    }

    #[Test]
    public function includesAdditionalDataAttributes(): void
    {
        $this->variableProvider->add('component', ['fullName' => 'Collapsible.Root']);
        $this->variableProvider->add('rootId', '«f1»');

        $this->viewHelper->setArguments([
            'name' => 'trigger',
            'asArray' => false,
            'data' => [
                'action' => 'toggle',
                'state' => 'collapsed',
            ],
        ]);

        $result = $this->viewHelper->render();

        $this->assertStringContainsString('data-action="toggle"', $result);
        $this->assertStringContainsString('data-state="collapsed"', $result);
    }

    #[Test]
    public function returnsArrayWhenAsArrayIsTrue(): void
    {
        $this->variableProvider->add('component', ['fullName' => 'Collapsible.Root']);
        $this->variableProvider->add('rootId', '«f1»');

        $this->viewHelper->setArguments([
            'name' => 'trigger',
            'asArray' => true,
            'data' => [],
        ]);

        $result = $this->viewHelper->render();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('data-scope', $result);
        $this->assertArrayHasKey('data-part', $result);
        $this->assertArrayHasKey('data-hydrate-collapsible', $result);
        $this->assertSame('collapsible', $result['data-scope']);
        $this->assertSame('trigger', $result['data-part']);
        $this->assertSame('«f1»', $result['data-hydrate-collapsible']);
    }

    #[Test]
    public function handlesAccordionComponentNameCorrectly(): void
    {
        $this->variableProvider->add('component', ['fullName' => 'Accordion.Item']);
        $this->variableProvider->add('context', ['rootId' => '«f1»']);

        $this->viewHelper->setArguments([
            'name' => 'item',
            'asArray' => false,
            'data' => [],
        ]);

        $result = $this->viewHelper->render();

        $this->assertStringContainsString('data-scope="accordion"', $result);
    }

    #[Test]
    public function handlesPrimitivesNamespaceCorrectly(): void
    {
        $this->variableProvider->add('component', ['fullName' => 'Primitives.Dialog.Root']);
        $this->variableProvider->add('context', ['rootId' => '«f1»']);

        $this->viewHelper->setArguments([
            'name' => 'root',
            'asArray' => false,
            'data' => [],
        ]);

        $result = $this->viewHelper->render();

        $this->assertStringContainsString('data-scope="dialog"', $result);
    }

    #[Test]
    public function throwsExceptionWhenUsedOutsideComponent(): void
    {
        $this->viewHelper->setArguments([
            'name' => 'trigger',
            'asArray' => false,
            'data' => [],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('can only be used inside a component context');

        $this->viewHelper->render();
    }

    #[Test]
    public function throwsExceptionWhenRootIdIsMissing(): void
    {
        $this->variableProvider->add('component', ['fullName' => 'Collapsible.Root']);

        $this->viewHelper->setArguments([
            'name' => 'trigger',
            'asArray' => false,
            'data' => [],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No rootId found');

        $this->viewHelper->render();
    }
}
