<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Domain\Dto\ListCollection;
use Jramke\FluidPrimitives\Enum\ComboboxInputBehavior;
use Jramke\FluidPrimitives\Enum\PopupType;
use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Registry\PortalRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class ComboboxRenderingTest extends FunctionalTestCase
{
    #[Test]
    public function rendersClosedStateByDefault(): void
    {
        // Regression test: `defaultOpen` used to have no explicit default, so `context.defaultOpen`
        // was `null` rather than `false` when unset. TYPO3 Fluid's inline ternary shorthand
        // (`{x ? a : b}`) treats a bare `null` as truthy - unlike `f:if`, which correctly treats it
        // as falsy - so the trigger rendered a contradictory `aria-expanded="false" data-state="open"`
        // by default.
        $html = $this->renderTemplate('
            <primitives:combobox.root>
                <primitives:combobox.trigger>Toggle</primitives:combobox.trigger>
            </primitives:combobox.root>
        ');

        $this->assertStringContainsString('data-state="closed"', $html);
        $this->assertStringContainsString('aria-expanded="false"', $html);
        $this->assertStringNotContainsString('data-state="open"', $html);
    }

    #[Test]
    public function resolvesInputBehaviorEnumDefaultInsteadOfTheRawConstantExpression(): void
    {
        // Regression test: `f:constant('Enum::Case')` (name passed positionally) silently fails to
        // parse as a ViewHelper call inside a `ui:prop` default - Fluid falls back to treating the
        // whole `{...}` expression as literal text, so `context.inputBehavior`/the hydrated prop
        // ended up as the raw string "{f:constant('...')}" instead of the enum's `none` value. Only
        // reproduces with the argument passed positionally; `f:constant(name: 'Enum::Case')` (used
        // by every other enum-typed prop default in this codebase) is unaffected.
        $this->renderTemplate('
            <primitives:combobox.root>
                <primitives:combobox.trigger>Toggle</primitives:combobox.trigger>
            </primitives:combobox.root>
        ');

        $hydrationData = HydrationRegistry::getInstance()->getAll()['primitives'] ?? [];
        $comboboxData = array_values($hydrationData['combobox'])[0];

        $this->assertSame(ComboboxInputBehavior::None->value, $comboboxData['props']['inputBehavior']);
    }

    #[Test]
    public function rendersTheListboxOnTheListPartInsideAPresentationalContent(): void
    {
        $html = $this->renderTemplate('
            <primitives:combobox.root multiple="{true}">
                <primitives:combobox.positioner>
                    <primitives:combobox.content>
                        <primitives:combobox.list>List</primitives:combobox.list>
                    </primitives:combobox.content>
                </primitives:combobox.positioner>
            </primitives:combobox.root>
        ');

        preg_match('/<div[^>]*data-combobox-content="[^"]+"[^>]*>/', $html, $content);
        preg_match('/<div[^>]*data-combobox-list="[^"]+"[^>]*>/', $html, $list);

        $this->assertStringContainsString('role="presentation"', $content[0]);
        $this->assertStringNotContainsString('aria-multiselectable', $content[0]);
        $this->assertStringContainsString('role="listbox"', $list[0]);
        $this->assertStringContainsString('aria-multiselectable="true"', $list[0]);
    }

    #[Test]
    public function shipsThePopupTypeToTheClientDefaultingToListbox(): void
    {
        $this->renderTemplate('
            <primitives:combobox.root>
                <primitives:combobox.trigger>Toggle</primitives:combobox.trigger>
            </primitives:combobox.root>
        ');

        $hydrationData = HydrationRegistry::getInstance()->getAll()['primitives'] ?? [];
        $comboboxData = array_values($hydrationData['combobox'])[0];

        $this->assertSame(PopupType::Listbox->value, $comboboxData['props']['popupType']);
    }

    #[Test]
    public function rendersTheTriggerAndContentForThePopupType(): void
    {
        $render = fn(string $attributes): string => $this->renderTemplate('
            <primitives:combobox.root ' .
        $attributes .
        '>
                <primitives:combobox.trigger>Open</primitives:combobox.trigger>
                <primitives:combobox.positioner>
                    <primitives:combobox.content><primitives:combobox.list>List</primitives:combobox.list></primitives:combobox.content>
                </primitives:combobox.positioner>
            </primitives:combobox.root>
        ');

        $listbox = $render('');
        $dialog = $render('popupType="{f:constant(name: \'Jramke\FluidPrimitives\Enum\PopupType::Dialog\')}"');

        $this->assertStringContainsString('aria-haspopup="listbox"', $listbox);
        $this->assertMatchesRegularExpression('/<div[^>]*role="presentation"[^>]*data-combobox-content="/', $listbox);
        $this->assertStringContainsString('aria-haspopup="dialog"', $dialog);
        $this->assertMatchesRegularExpression('/<div[^>]*role="dialog"[^>]*data-combobox-content="/', $dialog);
    }

    #[Test]
    public function resolvesTheTriggerFocusFromItsPropElseThePopupType(): void
    {
        $render = fn(string $rootAttributes, string $triggerAttributes = ''): string => $this->renderTemplate(
            '
            <primitives:combobox.root ' .
            $rootAttributes .
            '>
                <primitives:combobox.trigger ' .
            $triggerAttributes .
            '>Open</primitives:combobox.trigger>
            </primitives:combobox.root>
        ',
        );
        $trigger = static function (string $html): string {
            preg_match('/<button[^>]*data-combobox-trigger="[^"]+"[^>]*>/', $html, $match);

            return $match[0];
        };
        $dialog = 'popupType="{f:constant(name: \'Jramke\FluidPrimitives\Enum\PopupType::Dialog\')}"';

        $this->assertStringContainsString('tabindex="-1"', $trigger($render('')));
        $this->assertStringNotContainsString('data-focusable', $trigger($render('')));

        $this->assertStringContainsString('data-focusable', $trigger($render($dialog)));
        $this->assertStringNotContainsString('tabindex', $trigger($render($dialog)));

        $this->assertStringContainsString('data-focusable', $trigger($render('', 'focusable="{true}"')));
        $this->assertStringContainsString('tabindex="-1"', $trigger($render($dialog, 'focusable="{false}"')));
    }

    #[Test]
    public function rendersItemFromARealCollectionItem(): void
    {
        $collection = new ListCollection([
            ['value' => 'berlin', 'label' => 'Berlin'],
        ]);

        $html = $this->renderTemplate('
            <primitives:combobox.root collection="{collection}">
                <primitives:combobox.content>
                    <f:for each="{collection.items}" as="item">
                        <primitives:combobox.item item="{item}">
                            <primitives:combobox.itemText>{item.label}</primitives:combobox.itemText>
                        </primitives:combobox.item>
                    </f:for>
                </primitives:combobox.content>
            </primitives:combobox.root>
        ', ['collection' => $collection]);

        $this->assertStringContainsString('data-combobox-item="', $html);
        $this->assertStringContainsString('data-value="berlin"', $html);
        $this->assertStringContainsString('Berlin', $html);
    }

    #[Test]
    public function rendersItemInsideTemplateWithNoAriaSelected(): void
    {
        $collection = new ListCollection([]);

        $html = $this->renderTemplate('
            <primitives:combobox.root collection="{collection}">
                <primitives:combobox.content>
                    <ui:template name="itemTemplate" context="combobox">
                        <primitives:combobox.item>
                            <span>placeholder</span>
                        </primitives:combobox.item>
                    </ui:template>
                </primitives:combobox.content>
            </primitives:combobox.root>
        ', ['collection' => $collection]);

        $this->assertStringContainsString('data-combobox-item="', $html);
        $this->assertStringContainsString('data-state="unchecked"', $html);
        $this->assertStringNotContainsString('aria-selected', $html);
    }

    #[Test]
    public function throwsWhenItemIsMissingOutsideTemplate(): void
    {
        $collection = new ListCollection([]);

        $this->expectExceptionMessage("requires an 'item' prop, unless used inside a ui:template block");

        $this->renderTemplate('
            <primitives:combobox.root collection="{collection}">
                <primitives:combobox.content>
                    <primitives:combobox.item>
                        <span>content</span>
                    </primitives:combobox.item>
                </primitives:combobox.content>
            </primitives:combobox.root>
        ', ['collection' => $collection]);
    }

    #[Test]
    public function acceptsAnEmptyCollectionForPureAsyncUsage(): void
    {
        $html = $this->renderTemplate('
            <ui:listCollection as="collection" />
            <primitives:combobox.root collection="{collection}">
                <primitives:combobox.content></primitives:combobox.content>
            </primitives:combobox.root>
        ');

        $this->assertStringContainsString('data-combobox-root="', $html);
        $this->assertStringContainsString('data-empty="true"', $html);
    }

    #[Test]
    public function rendersWithCollectionOmittedEntirelyForPureAsyncUsage(): void
    {
        // No `collection` attribute at all (not even an explicit empty ui:listCollection) -
        // exercises ComboboxContext::getCollection() being called from Fluid's own property-path
        // resolution (context.collection.size in Content.html) while genuinely null. Regression
        // test for a protected-method visibility crash this used to trigger.
        $html = $this->renderTemplate('
            <primitives:combobox.root searchUrl="/search">
                <primitives:combobox.content></primitives:combobox.content>
            </primitives:combobox.root>
        ');

        $this->assertStringContainsString('data-combobox-root="', $html);
        $this->assertStringContainsString('data-empty="true"', $html);
    }

    #[Test]
    public function rendersEmptyVisibleWhenCollectionHasNoItems(): void
    {
        $collection = new ListCollection([]);

        $html = $this->renderTemplate('
            <primitives:combobox.root collection="{collection}">
                <primitives:combobox.content>
                    <primitives:combobox.empty>No results found</primitives:combobox.empty>
                </primitives:combobox.content>
            </primitives:combobox.root>
        ', ['collection' => $collection]);

        $this->assertStringContainsString('data-combobox-empty="', $html);
        $this->assertStringContainsString('role="presentation"', $html);
        $this->assertStringContainsString('No results found', $html);
        $this->assertDoesNotMatchRegularExpression('/<div[^>]*\bhidden\b[^>]*data-combobox-empty="/', $html);
    }

    #[Test]
    public function rendersEmptyHiddenWhenCollectionHasItems(): void
    {
        $collection = new ListCollection([
            ['value' => 'berlin', 'label' => 'Berlin'],
        ]);

        $html = $this->renderTemplate('
            <primitives:combobox.root collection="{collection}">
                <primitives:combobox.content>
                    <primitives:combobox.empty>No results found</primitives:combobox.empty>
                </primitives:combobox.content>
            </primitives:combobox.root>
        ', ['collection' => $collection]);

        $this->assertMatchesRegularExpression('/<div[^>]*\bhidden\b[^>]*data-combobox-empty="/', $html);
    }

    #[Test]
    public function refWithExplicitContextResolvesInsideHandAuthoredSlotContent(): void
    {
        // Unlike the itemTemplate case, this span is neither a component's own template body
        // nor wrapped in ui:template - it's plain, hand-authored slot content, several layers deep
        // (root -> content -> empty). `context` targets the "combobox" ancestor explicitly instead
        // of relying on whichever component happens to be ambiently active.
        $collection = new ListCollection([]);

        $html = $this->renderTemplate('
            <primitives:combobox.root collection="{collection}">
                <primitives:combobox.content>
                    <primitives:combobox.empty>
                        <span {ui:ref(name: \'statusText\', context: \'combobox\')}>Loading…</span>
                    </primitives:combobox.empty>
                </primitives:combobox.content>
            </primitives:combobox.root>
        ', ['collection' => $collection]);

        $this->assertMatchesRegularExpression(
            '/<span[^>]*data-combobox-status-text="[^"]*"[^>]*>Loading…<\/span>/',
            $html,
        );
    }

    #[Test]
    public function refWithUnknownExplicitContextThrows(): void
    {
        $collection = new ListCollection([]);

        $this->expectExceptionMessage('ui:ref could not find an active "select" component to attach to.');

        $this->renderTemplate('
            <primitives:combobox.root collection="{collection}">
                <primitives:combobox.content>
                    <primitives:combobox.empty>
                        <span {ui:ref(name: \'statusText\', context: \'select\')}>Loading…</span>
                    </primitives:combobox.empty>
                </primitives:combobox.content>
            </primitives:combobox.root>
        ', ['collection' => $collection]);
    }

    #[Test]
    public function itemTemplateProducesATemplateElementWithMarkerlessRefs(): void
    {
        $collection = new ListCollection([]);

        $html = $this->renderTemplate('
            <primitives:combobox.root collection="{collection}">
                <primitives:combobox.content>
                    <ui:template name="itemTemplate" context="combobox">
                        <primitives:combobox.item>
                            <primitives:combobox.itemText>
                                <span {ui:ref(name: \'title\')}></span>
                            </primitives:combobox.itemText>
                        </primitives:combobox.item>
                    </ui:template>
                </primitives:combobox.content>
            </primitives:combobox.root>
        ', ['collection' => $collection]);

        $this->assertMatchesRegularExpression('/<template data-combobox-item-template="[^"]+">/', $html);
        $this->assertMatchesRegularExpression('/<span data-combobox-title="[^"]+"><\/span>/', $html);
    }

    #[Test]
    public function rendersNoHiddenInputWhenNothingIsSelected(): void
    {
        // Regression test: no `defaultValue` at all must not crash (ComboboxContext::getDefaultValue()
        // returning null) and must render zero hidden inputs - unlike a native `<select>`, there's no
        // "first option gets auto-selected" quirk to work around here.
        $html = $this->renderTemplate('
            <primitives:combobox.root>
                <primitives:combobox.hiddenInput />
            </primitives:combobox.root>
        ');

        $this->assertStringContainsString('data-combobox-root="', $html);
        $this->assertStringNotContainsString('data-combobox-hidden-input="', $html);
    }

    #[Test]
    public function hiddenInputSubmitsTheItemValueInsteadOfItsLabel(): void
    {
        $collection = new ListCollection([
            ['value' => 'us', 'label' => 'United States'],
            ['value' => 'de', 'label' => 'Germany'],
        ]);

        $html = $this->renderTemplate('
            <primitives:combobox.root collection="{collection}" name="country" defaultValue="us">
                <primitives:combobox.hiddenInput />
            </primitives:combobox.root>
        ', ['collection' => $collection]);

        $this->assertStringContainsString('data-combobox-hidden-input="', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*type="text"[^>]*name="country"[^>]*value="us"/', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
        $this->assertStringContainsString('tabindex="-1"', $html);
        $this->assertStringNotContainsString('value="United States"', $html);
        $this->assertStringNotContainsString('United States', $html);
    }

    #[Test]
    public function rendersOneHiddenInputPerSelectedValueWhenMultiple(): void
    {
        $collection = new ListCollection([
            ['value' => 'us', 'label' => 'United States'],
            ['value' => 'de', 'label' => 'Germany'],
            ['value' => 'fr', 'label' => 'France'],
        ]);

        $html = $this->renderTemplate('
            <primitives:combobox.root collection="{collection}" name="countries[]" multiple="{true}" defaultValue="{0: \'us\', 1: \'fr\'}">
                <primitives:combobox.hiddenInput />
            </primitives:combobox.root>
        ', ['collection' => $collection]);

        $this->assertMatchesRegularExpression('/<input[^>]*value="us"/', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*value="fr"/', $html);
        $this->assertStringNotContainsString('value="de"', $html);
        $this->assertSame(2, substr_count($html, 'data-combobox-hidden-input="'));
    }

    #[Test]
    public function itemTextAndItemIndicatorAutoDetectInsideTemplateWithNoLeakedProp(): void
    {
        $collection = new ListCollection([]);

        $html = $this->renderTemplate('
            <primitives:combobox.root collection="{collection}">
                <primitives:combobox.content>
                    <ui:template name="itemTemplate" context="combobox">
                        <primitives:combobox.item>
                            <primitives:combobox.itemText>
                                <span {ui:ref(name: \'title\')}></span>
                            </primitives:combobox.itemText>
                            <primitives:combobox.itemIndicator />
                        </primitives:combobox.item>
                    </ui:template>
                </primitives:combobox.content>
            </primitives:combobox.root>
        ', ['collection' => $collection]);

        $this->assertStringContainsString('data-combobox-item-text="', $html);
        $this->assertStringContainsString('data-combobox-item-indicator="', $html);
        $this->assertStringNotContainsString('renderedOnClient', $html);
    }

    /**
     * Combobox's Root, like Select's, renders a real wrapping `<div>` - so it's still detected for
     * hydration purely from that inline root ref, with Content itself portaled away entirely.
     */
    #[Test]
    public function rendersContentInsidePortalAndStillRegistersForHydration(): void
    {
        HydrationRegistry::getInstance()->clear();
        PortalRegistry::getInstance()->clearAll();

        $collection = new ListCollection([
            ['value' => 'berlin', 'label' => 'Berlin'],
        ]);

        $html = $this->renderTemplate('
            <primitives:combobox.root collection="{collection}" rootId="portaled-combobox">
                <primitives:combobox.trigger>Toggle</primitives:combobox.trigger>
                <ui:portal>
                    <primitives:combobox.content>
                        <f:for each="{collection.items}" as="item">
                            <primitives:combobox.item item="{item}">
                                <primitives:combobox.itemText>{item.label}</primitives:combobox.itemText>
                            </primitives:combobox.item>
                        </f:for>
                    </primitives:combobox.content>
                </ui:portal>
            </primitives:combobox.root>
        ', ['collection' => $collection]);

        $this->assertStringNotContainsString('data-combobox-content="', $html);

        $portaled = implode('', PortalRegistry::getInstance()->getAllByName('default'));
        $this->assertStringContainsString('data-combobox-content="portaled-combobox"', $portaled);
        $this->assertStringContainsString('Berlin', $portaled);

        $hydrationData = HydrationRegistry::getInstance()->getAll()['primitives'] ?? [];
        $this->assertArrayHasKey('portaled-combobox', $hydrationData['combobox'] ?? []);
    }
}
