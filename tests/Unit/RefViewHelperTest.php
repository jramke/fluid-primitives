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
        $this->variableProvider->add('component', ['fullName' => 'Collapsible.Root', 'baseName' => 'collapsible']);
        $this->variableProvider->add('rootId', '«f1»');
        $this->variableProvider->add('context', ['ids' => []]);

        $this->viewHelper->setArguments([
            'name' => 'trigger',
            'asArray' => false,
            'data' => [],
            'value' => null,
        ]);

        $result = $this->viewHelper->render();

        $this->assertStringContainsString('id="collapsible:«f1»:trigger"', $result);
        $this->assertStringContainsString('data-scope="collapsible"', $result);
        $this->assertStringContainsString('data-part="trigger"', $result);
        $this->assertStringNotContainsString('data-hydrate-', $result);
    }

    #[Test]
    public function rendersRootRefCorrectly(): void
    {
        $this->variableProvider->add('component', ['fullName' => 'Collapsible.Root', 'baseName' => 'collapsible']);
        $this->variableProvider->add('rootId', '«f1»');
        $this->variableProvider->add('context', ['ids' => []]);

        $this->viewHelper->setArguments([
            'name' => 'root',
            'asArray' => false,
            'data' => [],
            'value' => null,
        ]);

        $result = $this->viewHelper->render();

        $this->assertStringContainsString('id="collapsible:«f1»"', $result);
        $this->assertStringContainsString('data-part="root"', $result);
        $this->assertStringNotContainsString('data-hydrate-', $result);
    }

    #[Test]
    public function generatesIdWithValueForMultiInstancePart(): void
    {
        $this->variableProvider->add('component', ['fullName' => 'Accordion.Item', 'baseName' => 'accordion']);
        $this->variableProvider->add('context', ['rootId' => '«f2»', 'ids' => []]);

        $this->viewHelper->setArguments([
            'name' => 'item',
            'asArray' => false,
            'data' => [],
            'value' => 'my-item',
        ]);

        $result = $this->viewHelper->render();

        $this->assertStringContainsString('id="accordion:«f2»:item:my-item"', $result);
        $this->assertStringContainsString('data-part="item"', $result);
    }

    #[Test]
    public function usesExplicitIdFromIdsConfiguration(): void
    {
        $this->variableProvider->add('component', ['fullName' => 'Collapsible.Root', 'baseName' => 'collapsible']);
        $this->variableProvider->add('rootId', '«f1»');
        $this->variableProvider->add('context', ['ids' => ['trigger' => 'my-custom-trigger-id']]);

        $this->viewHelper->setArguments([
            'name' => 'trigger',
            'asArray' => false,
            'data' => [],
            'value' => null,
        ]);

        $result = $this->viewHelper->render();

        $this->assertStringContainsString('id="my-custom-trigger-id"', $result);
    }

    #[Test]
    public function includesAdditionalDataAttributes(): void
    {
        $this->variableProvider->add('component', ['fullName' => 'Collapsible.Root', 'baseName' => 'collapsible']);
        $this->variableProvider->add('rootId', '«f1»');
        $this->variableProvider->add('context', ['ids' => []]);

        $this->viewHelper->setArguments([
            'name' => 'trigger',
            'asArray' => false,
            'data' => [
                'action' => 'toggle',
                'state' => 'collapsed',
            ],
            'value' => null,
        ]);

        $result = $this->viewHelper->render();

        $this->assertStringContainsString('data-action="toggle"', $result);
        $this->assertStringContainsString('data-state="collapsed"', $result);
    }

    #[Test]
    public function returnsArrayWhenAsArrayIsTrue(): void
    {
        $this->variableProvider->add('component', ['fullName' => 'Collapsible.Root', 'baseName' => 'collapsible']);
        $this->variableProvider->add('rootId', '«f1»');
        $this->variableProvider->add('context', ['ids' => []]);

        $this->viewHelper->setArguments([
            'name' => 'trigger',
            'asArray' => true,
            'data' => [],
            'value' => null,
        ]);

        $result = $this->viewHelper->render();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('data-scope', $result);
        $this->assertArrayHasKey('data-part', $result);
        $this->assertArrayNotHasKey('data-hydrate-collapsible', $result);
        $this->assertSame('collapsible:«f1»:trigger', $result['id']);
        $this->assertSame('collapsible', $result['data-scope']);
        $this->assertSame('trigger', $result['data-part']);
    }

    #[Test]
    public function handlesAccordionComponentNameCorrectly(): void
    {
        $this->variableProvider->add('component', ['fullName' => 'Accordion.Item', 'baseName' => 'accordion']);
        $this->variableProvider->add('context', ['rootId' => '«f1»', 'ids' => []]);

        $this->viewHelper->setArguments([
            'name' => 'item',
            'asArray' => false,
            'data' => [],
            'value' => null,
        ]);

        $result = $this->viewHelper->render();

        $this->assertStringContainsString('data-scope="accordion"', $result);
    }

    #[Test]
    public function handlesPrimitivesNamespaceCorrectly(): void
    {
        $this->variableProvider->add('component', ['fullName' => 'Primitives.Dialog.Root', 'baseName' => 'dialog']);
        $this->variableProvider->add('context', ['rootId' => '«f1»', 'ids' => []]);

        $this->viewHelper->setArguments([
            'name' => 'root',
            'asArray' => false,
            'data' => [],
            'value' => null,
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
            'value' => null,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('can only be used inside a component context');

        $this->viewHelper->render();
    }

    #[Test]
    public function throwsExceptionWhenRootIdIsMissing(): void
    {
        $this->variableProvider->add('component', ['fullName' => 'Collapsible.Root', 'baseName' => 'collapsible']);

        $this->viewHelper->setArguments([
            'name' => 'trigger',
            'asArray' => false,
            'data' => [],
            'value' => null,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No rootId found');

        $this->viewHelper->render();
    }
}
