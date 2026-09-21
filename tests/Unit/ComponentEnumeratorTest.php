<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Tests\Fixtures\PlainComponentCollection;
use Jramke\FluidPrimitives\Tests\TestCase;
use Jramke\FluidPrimitives\Utility\ComponentEnumerator;
use PHPUnit\Framework\Attributes\Test;

final class ComponentEnumeratorTest extends TestCase
{
    /**
     * {@see PlainComponentCollection}'s `Card` is registered single-part (`<plain:card>`, no
     * `.root` suffix - see AsChildRenderingTest) and resolves to `Card/Card.fluid.html`, not
     * `Card/Root.fluid.html` - the on-disk shape `AbstractComponentCollection::resolveTemplateName()`
     * produces for any dot-less registration. enumerateRootComponents() must recognize this shape
     * too, not just the two-part `name.root`/`Root.fluid.html` convention.
     */
    #[Test]
    public function findsSinglePartRegisteredRootComponentWithoutRootSuffix(): void
    {
        $locations = ComponentEnumerator::enumerateRootComponents(new PlainComponentCollection());

        $this->assertCount(1, $locations);
        $this->assertSame('Card', $locations[0]->name);
        $this->assertSame('card', $locations[0]->viewHelperName);
    }
}
