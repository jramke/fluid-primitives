<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Domain\Model\ListCollection;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class ComboboxRenderingTest extends FunctionalTestCase
{
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

        $this->assertStringContainsString('data-part="item"', $html);
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
                    <ui:template name="item-template" component="combobox">
                        <primitives:combobox.item>
                            <span>placeholder</span>
                        </primitives:combobox.item>
                    </ui:template>
                </primitives:combobox.content>
            </primitives:combobox.root>
        ', ['collection' => $collection]);

        $this->assertStringContainsString('data-part="item"', $html);
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

        $this->assertStringContainsString('data-scope="combobox"', $html);
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

        $this->assertStringContainsString('data-scope="combobox"', $html);
        $this->assertStringContainsString('data-empty="true"', $html);
    }

    #[Test]
    public function itemTemplateProducesATemplateElementWithMarkerlessRefs(): void
    {
        $collection = new ListCollection([]);

        $html = $this->renderTemplate('
            <primitives:combobox.root collection="{collection}">
                <primitives:combobox.content>
                    <ui:template name="item-template" component="combobox">
                        <primitives:combobox.item>
                            <primitives:combobox.itemText>
                                <span {ui:ref(name: \'title\', withId: false)}></span>
                            </primitives:combobox.itemText>
                        </primitives:combobox.item>
                    </ui:template>
                </primitives:combobox.content>
            </primitives:combobox.root>
        ', ['collection' => $collection]);

        $this->assertMatchesRegularExpression('/<template id="combobox:[^"]*:item-template"/', $html);
        $this->assertStringContainsString('<span data-scope="combobox" data-part="title">', $html);
    }

    #[Test]
    public function itemTextAndItemIndicatorAutoDetectInsideTemplateWithNoLeakedProp(): void
    {
        $collection = new ListCollection([]);

        $html = $this->renderTemplate('
            <primitives:combobox.root collection="{collection}">
                <primitives:combobox.content>
                    <ui:template name="item-template" component="combobox">
                        <primitives:combobox.item>
                            <primitives:combobox.itemText>
                                <span {ui:ref(name: \'title\', withId: false)}></span>
                            </primitives:combobox.itemText>
                            <primitives:combobox.itemIndicator />
                        </primitives:combobox.item>
                    </ui:template>
                </primitives:combobox.content>
            </primitives:combobox.root>
        ', ['collection' => $collection]);

        $this->assertStringContainsString('data-part="item-text"', $html);
        $this->assertStringContainsString('data-part="item-indicator"', $html);
        $this->assertStringNotContainsString('renderedOnClient', $html);
    }
}
