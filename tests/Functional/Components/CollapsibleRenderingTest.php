<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class CollapsibleRenderingTest extends FunctionalTestCase
{
    #[Test]
    public function rendersCollapsibleRootWithDataAttributes(): void
    {
        $html = $this->renderTemplate('
            <primitives:collapsible.root>
                <primitives:collapsible.trigger>Toggle</primitives:collapsible.trigger>
                <primitives:collapsible.content>Hidden content</primitives:collapsible.content>
            </primitives:collapsible.root>
        ');

        $this->assertStringContainsString('data-scope="collapsible"', $html);
        $this->assertStringContainsString('data-part="root"', $html);
    }

    #[Test]
    public function rendersTriggerAsButtonWithAriaExpanded(): void
    {
        $html = $this->renderTemplate('
            <primitives:collapsible.root>
                <primitives:collapsible.trigger>Toggle</primitives:collapsible.trigger>
                <primitives:collapsible.content>Content</primitives:collapsible.content>
            </primitives:collapsible.root>
        ');

        $this->assertStringContainsString('<button', $html);
        $this->assertStringContainsString('type="button"', $html);
        $this->assertStringContainsString('data-part="trigger"', $html);
        $this->assertStringContainsString('aria-expanded="false"', $html);
    }

    #[Test]
    public function rendersExpandedStateWhenDefaultOpenTrue(): void
    {
        $html = $this->renderTemplate('
            <primitives:collapsible.root defaultOpen="{true}">
                <primitives:collapsible.trigger>Toggle</primitives:collapsible.trigger>
                <primitives:collapsible.content>Content</primitives:collapsible.content>
            </primitives:collapsible.root>
        ');

        $this->assertStringContainsString('aria-expanded="true"', $html);
        $this->assertStringContainsString('data-state="open"', $html);
    }

    #[Test]
    public function rendersHiddenByDefaultWhenClosed(): void
    {
        $html = $this->renderTemplate('
            <primitives:collapsible.root>
                <primitives:collapsible.trigger>Toggle</primitives:collapsible.trigger>
                <primitives:collapsible.content>Hidden content</primitives:collapsible.content>
            </primitives:collapsible.root>
        ');

        $this->assertStringContainsString('data-part="content"', $html);
        $this->assertMatchesRegularExpression('/data-part="content"[^>]*hidden/', $html);
    }

    #[Test]
    public function rendersVisibleWhenDefaultOpenTrue(): void
    {
        $html = $this->renderTemplate('
            <primitives:collapsible.root defaultOpen="{true}">
                <primitives:collapsible.trigger>Toggle</primitives:collapsible.trigger>
                <primitives:collapsible.content>Visible content</primitives:collapsible.content>
            </primitives:collapsible.root>
        ');

        $this->assertStringContainsString('Visible content', $html);
        $this->assertDoesNotMatchRegularExpression('/data-part="content"[^>]*hidden/', $html);
    }
}
