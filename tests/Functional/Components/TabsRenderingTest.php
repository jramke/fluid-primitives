<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class TabsRenderingTest extends FunctionalTestCase
{
    private const string TABS = '
        <primitives:tabs.root rootId="my-tabs" defaultValue="tab-2" %s>
            <primitives:tabs.list>
                <primitives:tabs.trigger value="tab-1">Tab 1</primitives:tabs.trigger>
                <primitives:tabs.trigger value="tab-2">Tab 2</primitives:tabs.trigger>
                <primitives:tabs.trigger value="tab-3" disabled="{true}">Tab 3</primitives:tabs.trigger>
            </primitives:tabs.list>
            <primitives:tabs.content value="tab-1">Content 1</primitives:tabs.content>
            <primitives:tabs.content value="tab-2">Content 2</primitives:tabs.content>
            <primitives:tabs.content value="tab-3">Content 3</primitives:tabs.content>
        </primitives:tabs.root>
    ';

    #[Test]
    public function marksEveryPartAndSelectsTheTriggerAndPanelOfTheDefaultValue(): void
    {
        $html = $this->renderTemplate(sprintf(self::TABS, ''));

        $this->assertSame(['my-tabs'], $this->valuesOf($html, 'data-tabs-root'));
        $list = $this->elementWith($html, 'data-tabs-list');
        $this->assertSame('tablist', $list->get('role'));
        $this->assertSame('my-tabs', $list->get('data-tabs-list'));

        $triggers = $this->elementsByValue($html, 'data-tabs-trigger');
        $panels = $this->elementsByValue($html, 'data-tabs-content');
        $this->assertSame(['tab-1', 'tab-2', 'tab-3'], array_keys($triggers));
        $this->assertSame(['tab-1', 'tab-2', 'tab-3'], array_keys($panels));

        foreach ($triggers as $value => $trigger) {
            $this->assertSame('button', $trigger->tag);
            $this->assertSame('tab', $trigger->get('role'));
            $this->assertSame('button', $trigger->get('type'));
            $this->assertSame($value === 'tab-2' ? 'true' : 'false', $trigger->get('aria-selected'), $value);
        }
        foreach ($panels as $value => $panel) {
            $this->assertSame('tabpanel', $panel->get('role'));
            $this->assertSame($value !== 'tab-2', $panel->has('hidden'), $value);
        }
    }

    #[Test]
    public function rendersADisabledTriggerNatively(): void
    {
        $triggers = $this->elementsByValue($this->renderTemplate(sprintf(self::TABS, '')), 'data-tabs-trigger');

        $disabled = $triggers['tab-3'];
        $this->assertTrue($disabled->has('disabled'));
        $this->assertSame('true', $disabled->get('aria-disabled'));
        $this->assertTrue($disabled->has('data-disabled'));
        $this->assertSame('-1', $disabled->get('tabindex'));

        $enabled = $triggers['tab-1'];
        $this->assertFalse($enabled->has('disabled'));
        $this->assertSame('false', $enabled->get('aria-disabled'));
        $this->assertFalse($enabled->has('data-disabled'));
    }

    #[Test]
    public function keepsTriggersAndPanelsOutOfTheTabOrderWithVirtualFocus(): void
    {
        $roving = $this->renderTemplate(sprintf(self::TABS, ''));
        $this->assertSame('0', $this->elementsByValue($roving, 'data-tabs-trigger')['tab-2']->get('tabindex'));
        $this->assertSame('-1', $this->elementsByValue($roving, 'data-tabs-trigger')['tab-1']->get('tabindex'));
        $this->assertSame('0', $this->elementsByValue($roving, 'data-tabs-content')['tab-2']->get('tabindex'));

        $virtual = $this->renderTemplate(sprintf(self::TABS, 'virtualFocus="{true}"'));
        $this->assertSame('-1', $this->elementsByValue($virtual, 'data-tabs-trigger')['tab-2']->get('tabindex'));
        $this->assertSame('-1', $this->elementsByValue($virtual, 'data-tabs-content')['tab-2']->get('tabindex'));
    }
}
