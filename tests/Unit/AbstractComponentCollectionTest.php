<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Component\AbstractComponentCollection;
use Jramke\FluidPrimitives\Exception\ReservedComponentNameException;
use Jramke\FluidPrimitives\Tests\Fixtures\RootDetectionComponentCollection;
use Jramke\FluidPrimitives\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3Fluid\Fluid\View\TemplatePaths;

final class AbstractComponentCollectionTest extends TestCase
{
    #[Test]
    public function listsEveryTemplateAndAProviderForEachRootWithParts(): void
    {
        $components = (new RootDetectionComponentCollection())->getAvailableComponents();
        sort($components);

        $this->assertSame(
            [
                'gadget',
                'gadget.part',
                'gadget.provider',
                'icon.copy',
                'icon.menu',
                'tier.gadget.gadget',
                'tier.gadget.part',
                'tier.gadget.provider',
                'tier.widget.item',
                'tier.widget.provider',
                'tier.widget.root',
                'widget.examples.demo',
                'widget.item',
                'widget.provider',
                'widget.root',
                'widget.trigger',
            ],
            $components,
        );
    }

    #[Test]
    public function aProviderTakesTheStateOfItsRootAndOnlyDetachablePartsLinkToIt(): void
    {
        $collection = new RootDetectionComponentCollection();

        $root = $collection->getComponentDefinition('widget.root')->getArgumentDefinitions();
        $provider = $collection->getComponentDefinition('widget.provider')->getArgumentDefinitions();

        $this->assertTrue($provider['id']->isRequired());
        $this->assertArrayHasKey('ids', $provider);
        foreach (['rootId', 'provider', 'spreadProps'] as $name) {
            $this->assertArrayHasKey($name, $root, $name);
            $this->assertArrayNotHasKey($name, $provider, $name);
        }
        $this->assertFalse($collection->getComponentDefinition('widget.provider')->additionalArgumentsAllowed());
        // a root without parts has no provider to link to
        $this->assertArrayNotHasKey(
            'provider',
            $collection->getComponentDefinition('icon.copy')->getArgumentDefinitions(),
        );
        $this->assertArrayHasKey(
            'provider',
            $collection->getComponentDefinition('tier.widget.root')->getArgumentDefinitions(),
        );

        $this->assertArrayHasKey(
            'provider',
            $collection->getComponentDefinition('widget.trigger')->getArgumentDefinitions(),
        );
        $this->assertArrayNotHasKey(
            'provider',
            $collection->getComponentDefinition('widget.item')->getArgumentDefinitions(),
        );
        $this->assertArrayNotHasKey(
            'rootId',
            $collection->getComponentDefinition('widget.item')->getArgumentDefinitions(),
        );
    }

    #[Test]
    public function rejectsATemplateNamedProviderNextToARoot(): void
    {
        $collection = new class extends AbstractComponentCollection {
            public function getTemplatePaths(): TemplatePaths
            {
                $templatePaths = new TemplatePaths();
                $templatePaths->setTemplateRootPaths([__DIR__ . '/../Fixtures/ReservedProvider']);
                return $templatePaths;
            }
        };

        $this->expectException(ReservedComponentNameException::class);
        $collection->getComponentDefinition('gadget.provider');
    }
}
