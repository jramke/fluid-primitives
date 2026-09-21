<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Hydration;

use Jramke\FluidPrimitives\Domain\Dto\ListCollection;
use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Tests\Fixtures\HydrationEdgeCasesCollection;
use Jramke\FluidPrimitives\Tests\Fixtures\NonConvertibleObject;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Covers {@see \Jramke\FluidPrimitives\Service\Component\ComponentHydrationCollector}'s object-prop
 * conversion hook and required-prop null-safety fix end to end, through real component rendering -
 * {@see \Jramke\FluidPrimitives\Tests\Fixtures\HydrationEdgeCasesCollection} exercises the two
 * fail-fast paths; {@see \Jramke\FluidPrimitives\Domain\Dto\ListCollection}'s own
 * `ClientTypeAwareInterface` implementation is covered by rendering Select/Combobox for real.
 */
final class ComponentHydrationCollectorTest extends FunctionalTestCase
{
    #[Test]
    public function throwsWhenARequiredClientPropIsExplicitlyNull(): void
    {
        $view = $this->getView();
        $view
            ->getRenderingContext()
            ->getViewHelperResolver()
            ->addNamespace('hydrationFixture', new HydrationEdgeCasesCollection());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('requiredProp');

        $this->renderTemplate('<hydrationFixture:edgeCases requiredProp="{explicitNull}" />', ['explicitNull' => null]);
    }

    #[Test]
    public function doesNotThrowWhenARequiredClientPropIsActuallyProvided(): void
    {
        $view = $this->getView();
        $view
            ->getRenderingContext()
            ->getViewHelperResolver()
            ->addNamespace('hydrationFixture', new HydrationEdgeCasesCollection());

        $html = $this->renderTemplate('<hydrationFixture:edgeCases requiredProp="a real value" />');

        $hydrationData = HydrationRegistry::getInstance()->getAll();
        $edgeCaseData = array_values($hydrationData['edge-cases'])[0];

        $this->assertStringContainsString('data-scope="edge-cases"', $html);
        $this->assertSame('a real value', $edgeCaseData['props']['requiredProp']);
    }

    #[Test]
    public function throwsWhenAClientPropIsAnUnconvertibleObject(): void
    {
        $view = $this->getView();
        $view
            ->getRenderingContext()
            ->getViewHelperResolver()
            ->addNamespace('hydrationFixture', new HydrationEdgeCasesCollection());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('objectProp');
        $this->expectExceptionMessage(NonConvertibleObject::class);

        $this->renderTemplate('<hydrationFixture:edgeCases requiredProp="value" objectProp="{obj}" />', [
            'obj' => new NonConvertibleObject(),
        ]);
    }

    /**
     * Select and Combobox both accept a `collection` prop typed as {@see ListCollection}, which
     * implements `ClientTypeAwareInterface` - confirms the collector leaves it as the real
     * `ListCollection` instance (not a lossy array conversion) for both, the same value its own
     * `jsonSerialize()` later turns into the wire payload the client's `ListCollectionData` type
     * describes.
     */
    #[Test]
    public function leavesListCollectionUntouchedForSelectAndCombobox(): void
    {
        $collection = new ListCollection([['value' => 'opt-1', 'label' => 'Option 1']]);

        $this->renderTemplate(
            '<primitives:select.root collection="{collection}"><primitives:select.control><primitives:select.trigger>Select</primitives:select.trigger></primitives:select.control></primitives:select.root>',
            ['collection' => $collection],
        );
        $selectData = array_values(HydrationRegistry::getInstance()->getAll()['select'])[0];
        $this->assertSame($collection, $selectData['props']['collection']);

        HydrationRegistry::getInstance()->clear();

        $this->renderTemplate('<primitives:combobox.root collection="{collection}" />', ['collection' => $collection]);
        $comboboxData = array_values(HydrationRegistry::getInstance()->getAll()['combobox'])[0];
        $this->assertSame($collection, $comboboxData['props']['collection']);
    }
}
