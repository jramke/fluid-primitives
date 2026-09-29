<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Domain\Dto\ComponentIdentity;
use Jramke\FluidPrimitives\Registry\ReferencedRootRegistry;
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
    public function rendersOnlyTheSinglePartAttributeAndMarksTheRootReferenced(): void
    {
        ReferencedRootRegistry::clear();
        $this->markAsDeclaredRootComponent('Collapsible.Root', 'collapsible');
        $this->variableProvider->add('rootId', '«f1»');
        $this->variableProvider->add('context', ['ids' => []]);

        $this->viewHelper->setArguments(['name' => 'trigger', 'asArray' => false, 'data' => [], 'value' => null]);
        $this->assertSame('data-collapsible-trigger="«f1»"', $this->viewHelper->render());

        $this->viewHelper->setArguments(['name' => 'root', 'asArray' => false, 'data' => [], 'value' => null]);
        $this->assertSame('data-collapsible-root="«f1»"', $this->viewHelper->render());

        $this->assertTrue(ReferencedRootRegistry::isReferenced('collapsible', '«f1»'));
        $this->assertFalse(ReferencedRootRegistry::isReferenced('collapsible', '«f2»'));
    }

    #[Test]
    public function rendersTheValueOfAMultiInstancePartAndKebabCasesItsName(): void
    {
        $this->variableProvider->add('component', ['fullName' => 'Dialog.CloseTrigger', 'baseName' => 'dialog']);
        $this->variableProvider->add('context', ['rootId' => '«f2»', 'ids' => []]);

        $this->viewHelper->setArguments([
            'name' => 'closeTrigger',
            'asArray' => false,
            'data' => [],
            'value' => 'my-item',
        ]);

        $this->assertSame('data-dialog-close-trigger="«f2»" data-value="my-item"', $this->viewHelper->render());
    }

    #[Test]
    public function rendersAnIdOnlyForAValuelessPartWithAnExplicitIdsEntry(): void
    {
        $this->markAsDeclaredRootComponent('Collapsible.Root', 'collapsible');
        $this->variableProvider->add('rootId', '«f1»');
        $this->variableProvider->add('context', ['ids' => ['trigger' => 'my-custom-trigger-id']]);

        $this->viewHelper->setArguments(['name' => 'trigger', 'asArray' => false, 'data' => [], 'value' => null]);
        $this->assertSame('id="my-custom-trigger-id" data-collapsible-trigger="«f1»"', $this->viewHelper->render());

        // An id can't be shared across a part's instances, so a valued part never gets one.
        $this->viewHelper->setArguments(['name' => 'trigger', 'asArray' => false, 'data' => [], 'value' => 'a']);
        $this->assertStringNotContainsString('id=', $this->viewHelper->render());
    }

    #[Test]
    public function attachesTheRefToAnotherInstancesRootIdIgnoringTheAmbientIds(): void
    {
        $this->markAsDeclaredRootComponent('Menu.Root', 'menu');
        $this->variableProvider->add('rootId', '«f1»');
        $this->variableProvider->add('context', ['ids' => ['triggerItem' => 'ambient-id']]);

        $this->viewHelper->setArguments([
            'name' => 'triggerItem',
            'asArray' => false,
            'data' => [],
            'value' => null,
            'rootId' => 'share-menu',
        ]);

        $this->assertSame('data-menu-trigger-item="share-menu"', $this->viewHelper->render());
    }

    #[Test]
    public function includesAdditionalDataAttributes(): void
    {
        $this->markAsDeclaredRootComponent('Collapsible.Root', 'collapsible');
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
        $this->markAsDeclaredRootComponent('Collapsible.Root', 'collapsible');
        $this->variableProvider->add('rootId', '«f1»');
        $this->variableProvider->add('context', ['ids' => []]);

        $this->viewHelper->setArguments([
            'name' => 'trigger',
            'asArray' => true,
            'data' => [],
            'value' => null,
        ]);

        $result = $this->viewHelper->render();

        $this->assertSame(['data-collapsible-trigger' => '«f1»'], $result);
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

        $this->assertStringContainsString('data-accordion-item="«f1»"', $result);
    }

    #[Test]
    public function replacesTheDotsOfATieredComponentNameInTheAttributeName(): void
    {
        $this->variableProvider->add('component', [
            'fullName' => 'CheckboxGroupExamples.SelectAll',
            'baseName' => 'checkboxGroupExamples.selectAll',
        ]);
        $this->variableProvider->add('context', ['rootId' => '«f1»', 'ids' => []]);

        $this->viewHelper->setArguments(['name' => 'root', 'asArray' => false, 'data' => [], 'value' => null]);

        $this->assertSame('data-checkbox-group-examples-select-all-root="«f1»"', $this->viewHelper->render());
    }

    #[Test]
    public function handlesPrimitivesNamespaceCorrectly(): void
    {
        // 'Primitives.Dialog.Root' is deliberately not declared-root here: the classic string rule
        // only checks segment[1] specifically ('Dialog', not 'Root'), a documented quirk covered by
        // ComponentRootUtilityTest::handlesPrimitivesNamespaceSecondPartIsNotRoot().
        $this->variableProvider->add('component', ['fullName' => 'Primitives.Dialog.Root', 'baseName' => 'dialog']);
        $this->variableProvider->add('context', ['rootId' => '«f1»', 'ids' => []]);

        $this->viewHelper->setArguments([
            'name' => 'root',
            'asArray' => false,
            'data' => [],
            'value' => null,
        ]);

        $result = $this->viewHelper->render();

        $this->assertStringContainsString('data-dialog-root="«f1»"', $result);
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
        $this->markAsDeclaredRootComponent('Collapsible.Root', 'collapsible');

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

    /**
     * Sets up both channels a real render would: the public `component` Fluid variable (`fullName`/
     * `baseName`, as a real render's {@see \Jramke\FluidPrimitives\Domain\Dto\ComponentIdentity::forView()}
     * would produce it) and the internal `ComponentIdentity` on the `ViewHelperVariableContainer`
     * (`isDeclaredRoot: true` - all {@see \Jramke\FluidPrimitives\Utility\ComponentUtility::getRootIdFromContext()}
     * needs to pick the bare `rootId` variable branch these tests exercise).
     */
    private function markAsDeclaredRootComponent(string $fullName, string $baseName): void
    {
        $this->variableProvider->add('component', ['fullName' => $fullName, 'baseName' => $baseName]);
        $this->renderingContext->getViewHelperVariableContainer()->add(
            ComponentIdentity::class,
            ComponentIdentity::VHVC_KEY,
            new ComponentIdentity(true, true, null, $baseName, $baseName, null),
        );
    }
}
