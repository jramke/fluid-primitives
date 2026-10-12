<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class ToggleRenderingTest extends FunctionalTestCase
{
    #[Test]
    public function rendersAnUnpressedButtonByDefaultAndPassesThePressedStateOnToTheIndicator(): void
    {
        $html = $this->renderTemplate($this->toggle('rootId="my-toggle"'));

        foreach (['root', 'indicator'] as $part) {
            $this->assertSame(['my-toggle'], $this->valuesOf($html, 'data-toggle-' . $part), $part);
        }
        $root = $this->elementWith($html, 'data-toggle-root');
        $this->assertSame('button', $root->tag);
        $this->assertSame('button', $root->get('type'));
        $this->assertSame('false', $root->get('aria-pressed'));
        $this->assertFalse($root->has('data-pressed'));
        $this->assertFalse($this->elementWith($html, 'data-toggle-indicator')->has('data-pressed'));

        $pressed = $this->renderTemplate($this->toggle('defaultPressed="{true}"'));
        $this->assertSame('true', $this->elementWith($pressed, 'data-toggle-root')->get('aria-pressed'));
        $this->assertTrue($this->elementWith($pressed, 'data-toggle-root')->has('data-pressed'));
        $this->assertTrue($this->elementWith($pressed, 'data-toggle-indicator')->has('data-pressed'));
    }

    #[Test]
    public function disablesTheButtonNativelyAndMarksTheIndicatorDisabledToo(): void
    {
        $html = $this->renderTemplate($this->toggle('disabled="{true}"'));

        $root = $this->elementWith($html, 'data-toggle-root');
        $this->assertTrue($root->has('disabled'));
        $this->assertTrue($root->has('data-disabled'));
        $this->assertTrue($this->elementWith($html, 'data-toggle-indicator')->has('data-disabled'));

        $enabled = $this->elementWith($this->renderTemplate($this->toggle('')), 'data-toggle-root');
        $this->assertFalse($enabled->has('disabled'));
        $this->assertFalse($enabled->has('data-disabled'));
    }

    #[Test]
    public function registersOneRootWithItsClientProps(): void
    {
        $this->renderTemplate($this->toggle('defaultPressed="{true}" disabled="{true}"'));

        $toggles = HydrationRegistry::getInstance()->getAll()['primitives']['toggle'];

        // the indicator is a part of the root, not a root of its own
        $this->assertCount(1, $toggles);
        $props = array_values($toggles)[0]['props'];
        $this->assertTrue($props['defaultPressed']);
        $this->assertTrue($props['disabled']);
    }

    private function toggle(string $rootArguments): string
    {
        return '
            <primitives:toggle.root ' . $rootArguments . '>
                <primitives:toggle.indicator>Bold</primitives:toggle.indicator>
            </primitives:toggle.root>
        ';
    }
}
