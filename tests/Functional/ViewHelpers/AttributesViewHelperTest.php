<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\ViewHelpers;

use Jramke\FluidPrimitives\Tests\Fixtures\PlainComponentCollection;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class AttributesViewHelperTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this
            ->getView()
            ->getRenderingContext()
            ->getViewHelperResolver()
            ->addNamespace('plain', new PlainComponentCollection());
    }

    #[Test]
    public function forwardsAttributeValuesToAnotherComponentWithoutSplittingThem(): void
    {
        // `htmlspecialchars` leaves spaces and `=` alone, so a forwarded value that was read back out of
        // rendered text would turn into attributes of its own
        $value = 'x onfocus=alert(1) autofocus tabindex=1 z';

        $html = $this->renderTemplate('<plain:forwarder title="{value}" data-sum="1=2">Text</plain:forwarder>', [
            'value' => $value,
        ]);

        $element = $this->elementWith($html, 'data-inner');
        $this->assertSame($value, $element->get('title'));
        $this->assertSame('1=2', $element->get('data-sum'));
        $this->assertFalse($element->has('onfocus'));
        $this->assertFalse($element->has('autofocus'));
        $this->assertNoRepeatedAttributes($html);
    }
}
