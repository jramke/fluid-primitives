<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class TabsRenderingTest extends FunctionalTestCase
{
    #[Test]
    public function rendersTabsRootWithDataAttributes(): void
    {
        $html = $this->renderTemplate('
            <primitives:tabs.root defaultValue="tab-1">
                <primitives:tabs.list>
                    <primitives:tabs.trigger value="tab-1">Tab 1</primitives:tabs.trigger>
                </primitives:tabs.list>
                <primitives:tabs.content value="tab-1">Content 1</primitives:tabs.content>
            </primitives:tabs.root>
        ');

        $this->assertStringContainsString('data-scope="tabs"', $html);
        $this->assertStringContainsString('data-part="root"', $html);
    }

    #[Test]
    public function rendersListWithTablistRole(): void
    {
        $html = $this->renderTemplate('
            <primitives:tabs.root defaultValue="tab-1">
                <primitives:tabs.list>
                    <primitives:tabs.trigger value="tab-1">Tab 1</primitives:tabs.trigger>
                </primitives:tabs.list>
                <primitives:tabs.content value="tab-1">Content 1</primitives:tabs.content>
            </primitives:tabs.root>
        ');

        $this->assertStringContainsString('role="tablist"', $html);
        $this->assertStringContainsString('data-part="list"', $html);
    }

    #[Test]
    public function rendersAsButtonWithTabRoleAndAria(): void
    {
        $html = $this->renderTemplate('
            <primitives:tabs.root defaultValue="tab-1">
                <primitives:tabs.list>
                    <primitives:tabs.trigger value="tab-1">Tab 1</primitives:tabs.trigger>
                </primitives:tabs.list>
                <primitives:tabs.content value="tab-1">Content 1</primitives:tabs.content>
            </primitives:tabs.root>
        ');

        $this->assertStringContainsString('<button', $html);
        $this->assertStringContainsString('role="tab"', $html);
        $this->assertStringContainsString('type="button"', $html);
        $this->assertStringContainsString('data-part="trigger"', $html);
    }

    #[Test]
    public function rendersSelectedStateCorrectlyBasedOnDefaultValue(): void
    {
        $html = $this->renderTemplate('
            <primitives:tabs.root defaultValue="tab-2">
                <primitives:tabs.list>
                    <primitives:tabs.trigger value="tab-1">Tab 1</primitives:tabs.trigger>
                    <primitives:tabs.trigger value="tab-2">Tab 2</primitives:tabs.trigger>
                </primitives:tabs.list>
                <primitives:tabs.content value="tab-1">Content 1</primitives:tabs.content>
                <primitives:tabs.content value="tab-2">Content 2</primitives:tabs.content>
            </primitives:tabs.root>
        ');

        $this->assertMatchesRegularExpression('/aria-selected="true"[^>]*data-value="tab-2"/', $html);
        $this->assertMatchesRegularExpression('/aria-selected="false"[^>]*data-value="tab-1"/', $html);
    }

    #[Test]
    public function rendersDisabledTriggerCorrectly(): void
    {
        $html = $this->renderTemplate('
            <primitives:tabs.root defaultValue="tab-1">
                <primitives:tabs.list>
                    <primitives:tabs.trigger value="tab-1">Tab 1</primitives:tabs.trigger>
                    <primitives:tabs.trigger value="tab-2" disabled="{true}">Tab 2 (Disabled)</primitives:tabs.trigger>
                </primitives:tabs.list>
                <primitives:tabs.content value="tab-1">Content 1</primitives:tabs.content>
                <primitives:tabs.content value="tab-2">Content 2</primitives:tabs.content>
            </primitives:tabs.root>
        ');

        $this->assertStringContainsString('disabled', $html);
        $this->assertStringContainsString('data-disabled', $html);
    }
}
