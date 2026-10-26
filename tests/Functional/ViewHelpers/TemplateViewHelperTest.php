<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\ViewHelpers;

use Jramke\FluidPrimitives\Domain\Dto\ListCollection;
use Jramke\FluidPrimitives\Exception\MissingContextException;
use Jramke\FluidPrimitives\Registry\NestedComponentRegistry;
use Jramke\FluidPrimitives\Tests\Fixtures\TemplateWrapperCollection;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class TemplateViewHelperTest extends FunctionalTestCase
{
    /**
     * A `ui:ref` reads the bare `rootId` variable when the template it sits in is itself a declared root
     * component (a docs example wrapping a combobox, say) - inside the stencil that must still be the
     * combobox's id, not the wrapper's own.
     */
    #[Test]
    public function resolvesRefsInsideTheStencilToTheEnclosingComponentNotAWrappingRoot(): void
    {
        $this
            ->getView()
            ->getRenderingContext()
            ->getViewHelperResolver()
            ->addNamespace('wrapperFixture', new TemplateWrapperCollection());

        $html = $this->renderTemplate('<wrapperFixture:wrapper rootId="outer-wrapper" />');

        $this->assertStringContainsString('data-combobox-title="inner-combobox"', $html);
        $this->assertStringNotContainsString('outer-wrapper', $html);
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
                <span data-after="[{rootId}][{context.rootId}][{component.fullName}]"></span>
            </primitives:combobox.root>
        ', ['collection' => $collection]);

        // Slot content is evaluated against the calling context, where none of these variables exist.
        // ui:template sets them around its own children, so they only stay empty if it puts them back.
        $this->assertSame('[][][]', $this->elementWith($html, 'data-after')->get('data-after'));
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

        $this->assertRenderThrows(
            MissingContextException::class,
            'ui:template could not find an active "select" component to attach to.',
            function () use ($collection): void {
                $this->renderTemplate('
            <primitives:combobox.root collection="{collection}">
                <ui:template name="itemTemplate" context="select">
                    <span>content</span>
                </ui:template>
            </primitives:combobox.root>
        ', ['collection' => $collection]);
            },
        );
    }

    #[Test]
    public function throwsWithoutContextWhenNoComponentIsAmbientlyActiveEither(): void
    {
        // No `context` argument, and this ui:template isn't inside any component's own template
        // body (it's plain top-level content in the test) - neither resolution path applies, so it
        // should fail with a message pointing at the fix, not the generic "no active X" one above.
        $this->assertRenderThrows(
            MissingContextException::class,
            'ui:template could not determine which component to attach to',
            function (): void {
                $this->renderTemplate('
            <ui:template name="itemTemplate">
                <span>content</span>
            </ui:template>
        ');
            },
        );
    }
}
