<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Registry\PortalRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class DatePickerRenderingTest extends FunctionalTestCase
{
    #[Test]
    public function rendersRootWithDataAttributes(): void
    {
        $html = $this->renderTemplate('
            <primitives:datePicker.root>
                <primitives:datePicker.control>
                    <primitives:datePicker.input />
                    <primitives:datePicker.trigger>Open</primitives:datePicker.trigger>
                </primitives:datePicker.control>
            </primitives:datePicker.root>
        ');

        $this->assertStringContainsString('data-scope="date-picker"', $html);
        $this->assertStringContainsString('data-part="root"', $html);
    }

    #[Test]
    public function rendersClosedStateByDefault(): void
    {
        // Regression test: `defaultOpen` used to have no explicit default, so `context.defaultOpen`
        // was `null` rather than `false` when unset - Fluid's inline ternary shorthand treats a bare
        // `null` as truthy (unlike `f:if`), which would render `data-state="open"` by default.
        $html = $this->renderTemplate('
            <primitives:datePicker.root>
                <primitives:datePicker.trigger>Open</primitives:datePicker.trigger>
            </primitives:datePicker.root>
        ');

        $this->assertStringContainsString('data-state="closed"', $html);
        $this->assertStringNotContainsString('data-state="open"', $html);
    }

    #[Test]
    public function rendersOpenStateWhenDefaultOpenIsSet(): void
    {
        $html = $this->renderTemplate('
            <primitives:datePicker.root defaultOpen="{true}">
                <primitives:datePicker.trigger>Open</primitives:datePicker.trigger>
            </primitives:datePicker.root>
        ');

        $this->assertStringContainsString('data-state="open"', $html);
    }

    #[Test]
    public function normalizesStringDefaultValueToArrayInHydrationData(): void
    {
        $this->renderTemplate('
            <primitives:datePicker.root defaultValue="2024-01-15">
                <primitives:datePicker.trigger>Open</primitives:datePicker.trigger>
            </primitives:datePicker.root>
        ');

        $hydrationData = HydrationRegistry::getInstance()->getAll()['primitives'] ?? [];
        $datePickerData = array_values($hydrationData['date-picker'])[0];

        $this->assertSame(['2024-01-15'], $datePickerData['props']['defaultValue']);
    }

    #[Test]
    public function passesArrayDefaultValueAsIs(): void
    {
        $this->renderTemplate('
            <primitives:datePicker.root defaultValue="{0: \'2024-01-15\', 1: \'2024-01-20\'}" selectionMode="range">
                <primitives:datePicker.trigger>Open</primitives:datePicker.trigger>
            </primitives:datePicker.root>
        ');

        $hydrationData = HydrationRegistry::getInstance()->getAll()['primitives'] ?? [];
        $datePickerData = array_values($hydrationData['date-picker'])[0];

        $this->assertSame(['2024-01-15', '2024-01-20'], $datePickerData['props']['defaultValue']);
    }

    #[Test]
    public function excludesDefaultValueFromHydrationWhenEmpty(): void
    {
        $this->renderTemplate('
            <primitives:datePicker.root>
                <primitives:datePicker.trigger>Open</primitives:datePicker.trigger>
            </primitives:datePicker.root>
        ');

        $hydrationData = HydrationRegistry::getInstance()->getAll()['primitives'] ?? [];
        $datePickerData = array_values($hydrationData['date-picker'])[0];

        $this->assertArrayNotHasKey('defaultValue', $datePickerData['props']);
    }

    #[Test]
    public function clearTriggerHiddenWithoutDefaultValueVisibleWithOne(): void
    {
        $htmlWithoutValue = $this->renderTemplate('
            <primitives:datePicker.root>
                <primitives:datePicker.clearTrigger>Clear</primitives:datePicker.clearTrigger>
            </primitives:datePicker.root>
        ');
        $this->assertStringContainsString('hidden="true"', $htmlWithoutValue);

        $htmlWithValue = $this->renderTemplate('
            <primitives:datePicker.root defaultValue="2024-01-15">
                <primitives:datePicker.clearTrigger>Clear</primitives:datePicker.clearTrigger>
            </primitives:datePicker.root>
        ');
        $this->assertStringNotContainsString('hidden="true"', $htmlWithValue);
    }

    #[Test]
    public function rendersTableHeaderAndBodyAsEmptyShellsWithoutServerRenderedCells(): void
    {
        $html = $this->renderTemplate('
            <primitives:datePicker.root>
                <primitives:datePicker.table view="day">
                    <primitives:datePicker.tableHeader view="day" />
                    <primitives:datePicker.tableBody view="day" />
                </primitives:datePicker.table>
            </primitives:datePicker.root>
        ');

        $this->assertStringContainsString('data-part="table"', $html);
        $this->assertStringContainsString('data-part="table-header"', $html);
        $this->assertStringContainsString('data-part="table-body"', $html);
        $this->assertStringContainsString('data-view="day"', $html);
        // Decision: the calendar grid is built entirely client-side - no server-rendered `<td>`/day cells.
        $this->assertStringNotContainsString('<td', $html);
    }

    #[Test]
    public function rendersContentInsidePortalAndStillRegistersForHydration(): void
    {
        HydrationRegistry::getInstance()->clear();
        PortalRegistry::getInstance()->clearAll();

        $html = $this->renderTemplate('
            <primitives:datePicker.root rootId="portaled-date-picker">
                <primitives:datePicker.trigger>Open</primitives:datePicker.trigger>
                <ui:portal>
                    <primitives:datePicker.content>
                        <primitives:datePicker.table view="day">
                            <primitives:datePicker.tableHeader view="day" />
                            <primitives:datePicker.tableBody view="day" />
                        </primitives:datePicker.table>
                    </primitives:datePicker.content>
                </ui:portal>
            </primitives:datePicker.root>
        ');

        $this->assertStringNotContainsString('data-part="content"', $html);

        $portaled = implode('', PortalRegistry::getInstance()->getAllByName('default'));
        $this->assertStringContainsString('data-part="content"', $portaled);

        $hydrationData = HydrationRegistry::getInstance()->getAll()['primitives'] ?? [];
        $this->assertArrayHasKey('portaled-date-picker', $hydrationData['date-picker'] ?? []);
    }
}
