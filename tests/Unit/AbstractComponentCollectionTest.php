<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Tests\Fixtures\RootDetectionComponentCollection;
use Jramke\FluidPrimitives\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class AbstractComponentCollectionTest extends TestCase
{
    #[Test]
    public function getComponentDefinitionAddsRootIdOnlyForRootComponents(): void
    {
        $collection = new RootDetectionComponentCollection();

        $this->assertArrayHasKey(
            'rootId',
            $collection->getComponentDefinition('widget.root')->getArgumentDefinitions(),
        );
        $this->assertArrayNotHasKey(
            'rootId',
            $collection->getComponentDefinition('widget.item')->getArgumentDefinitions(),
        );
        $this->assertArrayHasKey(
            'rootId',
            $collection->getComponentDefinition('widget.examples.demo')->getArgumentDefinitions(),
        );
    }

    #[Test]
    public function isDeclaredRootMirrorsTheRootIdArgumentDefinitionItReadsBackFrom(): void
    {
        $collection = new RootDetectionComponentCollection();

        $this->assertTrue($collection->isDeclaredRoot('widget.root'));
        $this->assertFalse($collection->isDeclaredRoot('widget.item'));
        $this->assertTrue($collection->isDeclaredRoot('widget.examples.demo'));
    }
}
