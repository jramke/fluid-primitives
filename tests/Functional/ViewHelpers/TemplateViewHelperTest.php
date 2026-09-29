<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\ViewHelpers;

use Jramke\FluidPrimitives\Domain\Dto\ListCollection;
use Jramke\FluidPrimitives\Registry\NestedComponentRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class TemplateViewHelperTest extends FunctionalTestCase
{
    #[Test]
    public function rendersATemplateElementWithRefAttributes(): void
    {
        $collection = new ListCollection([]);

        $html = $this->renderTemplate('
            <primitives:combobox.root collection="{collection}" rootId="my-combobox">
                <ui:template name="itemTemplate" context="combobox">
                    <span>static content</span>
                </ui:template>
            </primitives:combobox.root>
        ', ['collection' => $collection]);

        // The one ref attribute and nothing else (no id, no data-scope/data-part).
        $this->assertStringContainsString('<template data-combobox-item-template="my-combobox">', $html);
        $this->assertStringContainsString('<span>static content</span>', $html);
    }

    #[Test]
    public function makesBareUiRefResolveToTheEnclosingComponentInsideItsChildren(): void
    {
        $collection = new ListCollection([]);

        $html = $this->renderTemplate('
            <primitives:combobox.root collection="{collection}" rootId="my-combobox">
                <ui:template name="itemTemplate" context="combobox">
                    <span {ui:ref(name: \'title\')}></span>
                </ui:template>
            </primitives:combobox.root>
        ', ['collection' => $collection]);

        $this->assertStringContainsString('<span data-combobox-title="my-combobox">', $html);
    }

    #[Test]
    public function restoresThePreviousComponentContextAfterRendering(): void
    {
        $collection = new ListCollection([]);

        $html = $this->renderTemplate('
            <primitives:combobox.root collection="{collection}" rootId="my-combobox">
                <ui:template name="itemTemplate" context="combobox">
                    <span {ui:ref(name: \'title\')}></span>
                </ui:template>
                <primitives:combobox.input />
            </primitives:combobox.root>
        ', ['collection' => $collection]);

        // The real combobox.input part (a dedicated component tag, rendered right after the
        // ui:template block within the same root's slot) must still resolve to the real combobox
        // context - not whatever ui:template temporarily set - proving the finally-restoration works.
        $this->assertStringContainsString('data-combobox-input="my-combobox"', $html);
        $this->assertStringContainsString('role="combobox"', $html);
    }

    #[Test]
    public function defaultsToTheAmbientComponentWhenContextIsOmitted(): void
    {
        // No `context` on the inner ui:template - it's not slot content passed into another
        // component, it's written directly where a component's own template body already has
        // `component`/`context` ambiently active (here, the outer ui:template's own, standing in
        // for what a custom, single-file component's own body would have set up for real).
        $collection = new ListCollection([]);

        $html = $this->renderTemplate('
            <primitives:combobox.root collection="{collection}" rootId="my-combobox">
                <ui:template name="outerTemplate" context="combobox">
                    <ui:template name="innerTemplate">
                        <span {ui:ref(name: \'title\')}></span>
                    </ui:template>
                </ui:template>
            </primitives:combobox.root>
        ', ['collection' => $collection]);

        $this->assertStringContainsString('<template data-combobox-inner-template="my-combobox">', $html);
        $this->assertStringContainsString('<span data-combobox-title="my-combobox">', $html);
    }

    #[Test]
    public function recordsRootComponentsRenderedInsideItsChildrenAsNestedInItsOwnStencilKey(): void
    {
        $collection = new ListCollection([]);

        $html = $this->renderTemplate('
            <primitives:combobox.root collection="{collection}" rootId="my-combobox">
                <ui:template name="itemTemplate" context="combobox">
                    <primitives:field.root name="test" rootId="my-field">
                        <input type="text" />
                    </primitives:field.root>
                </ui:template>
            </primitives:combobox.root>
        ', ['collection' => $collection]);

        // The stencil's key is `<client>:<rootId>:<kebab-part>`, rebuilt client-side from the rendered
        // `<template data-combobox-item-template>`; the nested entry keeps the bare rootId.
        $this->assertStringContainsString('<template data-combobox-item-template="my-combobox">', $html);
        $this->assertStringContainsString('data-field-root="my-field"', $html);

        $byScope = NestedComponentRegistry::getInstance()->getNestedComponentsByScope();

        $this->assertSame(
            [['name' => 'primitives:field', 'id' => 'my-field']],
            $byScope['combobox:my-combobox:item-template'] ?? null,
        );
    }

    #[Test]
    public function throwsWhenNoMatchingComponentIsActive(): void
    {
        $collection = new ListCollection([]);

        $this->expectExceptionMessage('ui:template could not find an active "select" component to attach to.');

        $this->renderTemplate('
            <primitives:combobox.root collection="{collection}">
                <ui:template name="itemTemplate" context="select">
                    <span>content</span>
                </ui:template>
            </primitives:combobox.root>
        ', ['collection' => $collection]);
    }

    #[Test]
    public function throwsWithoutContextWhenNoComponentIsAmbientlyActiveEither(): void
    {
        // No `context` argument, and this ui:template isn't inside any component's own template
        // body (it's plain top-level content in the test) - neither resolution path applies, so it
        // should fail with a message pointing at the fix, not the generic "no active X" one above.
        $this->expectExceptionMessage('ui:template could not determine which component to attach to');

        $this->renderTemplate('
            <ui:template name="itemTemplate">
                <span>content</span>
            </ui:template>
        ');
    }
}
