<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Registry\NestedComponentRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class FieldArrayRenderingTest extends FunctionalTestCase
{
    #[Test]
    public function prefixesNestedFieldNameWithArrayNameAndRowIndex(): void
    {
        $html = $this->renderTemplate('
            <primitives:fieldArray.root name="people" itemCount="1">
                <primitives:fieldArray.itemGroup>
                    <primitives:fieldArray.item index="0">
                        <primitives:field.root name="firstName">
                            <primitives:field.control asChild="{true}">
                                <input type="text" />
                            </primitives:field.control>
                        </primitives:field.root>
                    </primitives:fieldArray.item>
                </primitives:fieldArray.itemGroup>
            </primitives:fieldArray.root>
        ');

        $this->assertStringContainsString('data-name="people[0][firstName]"', $html);
    }

    #[Test]
    public function registersTheRewrittenArrayPrefixedNameForClientHydrationTooNotTheRawArgument(): void
    {
        // Field's own client-registered `name` prop used to be read from the raw `name` argument
        // as literally typed on this tag ("firstName") rather than what `beforeRendering()`
        // rewrites it to once nested in a FieldArray row ("people[0][firstName]") - the same value
        // already correctly shown in `data-name` above. A field-aware primitive nested inside a
        // row (e.g. Input) mirrors this field's own registered `name` reactively so a later rename
        // propagates to it too - which meant that mechanism would overwrite an already-correct,
        // freshly-restamped row's own name right back to the un-prefixed one the moment this
        // field's machine first notified after mount.
        $html = $this->renderTemplate('
            <primitives:fieldArray.root name="people" itemCount="1">
                <primitives:fieldArray.itemGroup>
                    <primitives:fieldArray.item index="0">
                        <primitives:field.root name="firstName">
                            <primitives:field.control asChild="{true}">
                                <input type="text" />
                            </primitives:field.control>
                        </primitives:field.root>
                    </primitives:fieldArray.item>
                </primitives:fieldArray.itemGroup>
            </primitives:fieldArray.root>
        ');

        preg_match('/id="field:([^"]*)" data-scope="field" data-part="root"/', $html, $fieldMatches);
        $fieldRootId = $fieldMatches[1] ?? null;
        $this->assertNotNull($fieldRootId);

        $registeredProps = HydrationRegistry::getInstance()->get('primitives', 'field', $fieldRootId);

        $this->assertSame('people[0][firstName]', $registeredProps['props']['name'] ?? null);
    }

    #[Test]
    public function canonicalizesADottedChildFieldNameToNestedBracketsInsideARow(): void
    {
        // The array-prefix branch used to splice the child's raw `name` string in unprocessed, so a
        // dotted child name leaked a literal dot into one bracket segment (`people[0][address.city]`)
        // instead of nesting properly - re-parsing the child name before rebuilding the full bracket
        // path fixes that.
        $html = $this->renderTemplate('
            <primitives:fieldArray.root name="people" itemCount="1">
                <primitives:fieldArray.itemGroup>
                    <primitives:fieldArray.item index="0">
                        <primitives:field.root name="address.city">
                            <primitives:field.control asChild="{true}">
                                <input type="text" />
                            </primitives:field.control>
                        </primitives:field.root>
                    </primitives:fieldArray.item>
                </primitives:fieldArray.itemGroup>
            </primitives:fieldArray.root>
        ');

        $this->assertStringContainsString('data-name="people[0][address][city]"', $html);
    }

    #[Test]
    public function prefixesNestedFieldNameWithEmptyIndexInsideTheUnfilledStencil(): void
    {
        $html = $this->renderTemplate('
            <primitives:fieldArray.root name="people" itemCount="0">
                <primitives:fieldArray.itemTemplate>
                    <primitives:fieldArray.item>
                        <primitives:field.root name="firstName">
                            <primitives:field.control asChild="{true}">
                                <input type="text" />
                            </primitives:field.control>
                        </primitives:field.root>
                    </primitives:fieldArray.item>
                </primitives:fieldArray.itemTemplate>
            </primitives:fieldArray.root>
        ');

        $this->assertStringContainsString('data-name="people[][firstName]"', $html);
    }

    #[Test]
    public function prefixesEachServerRenderedRowWithItsOwnIndex(): void
    {
        $html = $this->renderTemplate('
            <primitives:fieldArray.root name="people" itemCount="2">
                <primitives:fieldArray.itemGroup>
                    <primitives:fieldArray.item index="0">
                        <primitives:field.root name="firstName">
                            <primitives:field.control asChild="{true}">
                                <input type="text" />
                            </primitives:field.control>
                        </primitives:field.root>
                    </primitives:fieldArray.item>
                    <primitives:fieldArray.item index="1">
                        <primitives:field.root name="firstName">
                            <primitives:field.control asChild="{true}">
                                <input type="text" />
                            </primitives:field.control>
                        </primitives:field.root>
                    </primitives:fieldArray.item>
                </primitives:fieldArray.itemGroup>
            </primitives:fieldArray.root>
        ');

        $this->assertStringContainsString('data-name="people[0][firstName]"', $html);
        $this->assertStringContainsString('data-name="people[1][firstName]"', $html);
    }

    #[Test]
    public function tracksEachServerRenderedRowsNestedFieldAgainstItsOwnRowKeyNotTheStencils(): void
    {
        $html = $this->renderTemplate('
            <primitives:fieldArray.root name="people" itemCount="2">
                <primitives:fieldArray.itemTemplate>
                    <primitives:fieldArray.item>
                        <primitives:field.root name="firstName">
                            <primitives:field.control asChild="{true}">
                                <input type="text" />
                            </primitives:field.control>
                        </primitives:field.root>
                    </primitives:fieldArray.item>
                </primitives:fieldArray.itemTemplate>
                <primitives:fieldArray.itemGroup>
                    <primitives:fieldArray.item index="0">
                        <primitives:field.root name="firstName">
                            <primitives:field.control asChild="{true}">
                                <input type="text" />
                            </primitives:field.control>
                        </primitives:field.root>
                    </primitives:fieldArray.item>
                    <primitives:fieldArray.item index="1">
                        <primitives:field.root name="firstName">
                            <primitives:field.control asChild="{true}">
                                <input type="text" />
                            </primitives:field.control>
                        </primitives:field.root>
                    </primitives:fieldArray.item>
                </primitives:fieldArray.itemGroup>
            </primitives:fieldArray.root>
        ');

        preg_match('/<template id="(field-array:[^"]*):itemTemplate"/', $html, $stencilMatches);
        $fieldArrayRootId = str_replace('field-array:', '', $stencilMatches[1] ?? '');
        $this->assertNotSame('', $fieldArrayRootId);

        preg_match_all('/id="field:([^"]*)" data-scope="field" data-part="root"/', $html, $fieldMatches);
        [$stencilFieldRootId, $row0FieldRootId, $row1FieldRootId] = $fieldMatches[1];

        $byScope = NestedComponentRegistry::getInstance()->getNestedComponentsByScope();

        $this->assertSame(
            [['name' => 'primitives:field', 'id' => $stencilFieldRootId]],
            $byScope["field-array:{$fieldArrayRootId}:itemTemplate"] ?? null,
        );
        $this->assertSame(
            [['name' => 'primitives:field', 'id' => $row0FieldRootId]],
            $byScope["field-array:{$fieldArrayRootId}:item:0"] ?? null,
        );
        $this->assertSame(
            [['name' => 'primitives:field', 'id' => $row1FieldRootId]],
            $byScope["field-array:{$fieldArrayRootId}:item:1"] ?? null,
        );
    }

    #[Test]
    public function fieldOutsideAFieldArrayIsUnaffected(): void
    {
        $html = $this->renderTemplate('
            <primitives:field.root name="email">
                <primitives:field.control asChild="{true}">
                    <input type="text" />
                </primitives:field.control>
            </primitives:field.root>
        ');

        $this->assertStringContainsString('data-name="email"', $html);
    }

    #[Test]
    public function hidesEmptyStateOnlyWhenItemCountIsPositive(): void
    {
        $hiddenEmptyState = '/<div[^>]*\bhidden\b[^>]*data-part="empty-state"[^>]*>/';

        $withRows = $this->renderTemplate('
            <primitives:fieldArray.root name="people" itemCount="1">
                <primitives:fieldArray.itemGroup>
                    <primitives:fieldArray.emptyState>Empty</primitives:fieldArray.emptyState>
                </primitives:fieldArray.itemGroup>
            </primitives:fieldArray.root>
        ');
        $this->assertMatchesRegularExpression($hiddenEmptyState, $withRows);

        $withoutRows = $this->renderTemplate('
            <primitives:fieldArray.root name="people" itemCount="0">
                <primitives:fieldArray.itemGroup>
                    <primitives:fieldArray.emptyState>Empty</primitives:fieldArray.emptyState>
                </primitives:fieldArray.itemGroup>
            </primitives:fieldArray.root>
        ');
        $this->assertDoesNotMatchRegularExpression($hiddenEmptyState, $withoutRows);
    }

    #[Test]
    public function disablesAddTriggerOnlyOnceMaxItemsIsReached(): void
    {
        $atMax = $this->renderTemplate('
            <primitives:fieldArray.root name="people" itemCount="3" maxItems="3">
                <primitives:fieldArray.addTrigger>Add</primitives:fieldArray.addTrigger>
            </primitives:fieldArray.root>
        ');
        $this->assertStringContainsString('aria-disabled="true"', $atMax);

        $belowMax = $this->renderTemplate('
            <primitives:fieldArray.root name="people" itemCount="2" maxItems="3">
                <primitives:fieldArray.addTrigger>Add</primitives:fieldArray.addTrigger>
            </primitives:fieldArray.root>
        ');
        $this->assertStringNotContainsString('aria-disabled="true"', $belowMax);
    }

    #[Test]
    public function neverDisablesRemoveTriggerInsideTheUnfilledStencil(): void
    {
        // `itemTemplate`'s own `removeTrigger` is never shown directly, only cloned client-side -
        // baking `aria-disabled` into it from whatever `itemCount`/`minItems` happen to be at
        // page-load time would stick on every clone, since a freshly-cloned node's first
        // `spreadProps()` call can't tell "already true in static HTML" apart from "never set" and
        // skips reconciling it. A row that was *just* added can never legitimately need its own
        // remove button pre-disabled (appending only ever makes removal more permissive), so the
        // stencil must render unconditionally enabled regardless of `minItems`.
        $html = $this->renderTemplate('
            <primitives:fieldArray.root name="people" itemCount="1" minItems="1">
                <primitives:fieldArray.itemTemplate>
                    <primitives:fieldArray.item>
                        <primitives:fieldArray.removeTrigger>Remove</primitives:fieldArray.removeTrigger>
                    </primitives:fieldArray.item>
                </primitives:fieldArray.itemTemplate>
            </primitives:fieldArray.root>
        ');

        $this->assertStringNotContainsString('aria-disabled="true"', $html);
    }

    #[Test]
    public function disablesRemoveTriggerOnlyOnceMinItemsIsReached(): void
    {
        $atMin = $this->renderTemplate('
            <primitives:fieldArray.root name="people" itemCount="1" minItems="1">
                <primitives:fieldArray.itemGroup>
                    <primitives:fieldArray.item index="0">
                        <primitives:fieldArray.removeTrigger>Remove</primitives:fieldArray.removeTrigger>
                    </primitives:fieldArray.item>
                </primitives:fieldArray.itemGroup>
            </primitives:fieldArray.root>
        ');
        $this->assertStringContainsString('aria-disabled="true"', $atMin);

        $aboveMin = $this->renderTemplate('
            <primitives:fieldArray.root name="people" itemCount="2" minItems="1">
                <primitives:fieldArray.itemGroup>
                    <primitives:fieldArray.item index="0">
                        <primitives:fieldArray.removeTrigger>Remove</primitives:fieldArray.removeTrigger>
                    </primitives:fieldArray.item>
                </primitives:fieldArray.itemGroup>
            </primitives:fieldArray.root>
        ');
        $this->assertStringNotContainsString('aria-disabled="true"', $aboveMin);
    }
}
