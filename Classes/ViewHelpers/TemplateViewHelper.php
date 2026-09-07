<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\ViewHelpers;

use Jramke\FluidPrimitives\Contexts\ComponentContextInterface;
use Jramke\FluidPrimitives\Domain\Model\TagAttributes;
use Jramke\FluidPrimitives\Service\ContextService;
use Jramke\FluidPrimitives\Utility\ComponentUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Wraps its children in a `<template>` element and, for the duration of rendering them, makes
 * them behave as if they were genuinely nested inside the named enclosing component - so `ui:ref`
 * (used completely unmodified) resolves correctly even though this content is, structurally,
 * plain slot content rather than a dedicated component's own template body.
 *
 * This is needed because a component's slot content (the markup a consumer writes between two
 * component tags, e.g. everything inside `<ui:combobox.content>...</ui:combobox.content>`) is
 * always evaluated against the *calling* rendering context, not the component's own internal one.
 * A bare `ui:ref` call written directly inside such slot content therefore doesn't, by default,
 * know which component (or rootId) it belongs to.
 *
 * `ui:template` fixes this generically for any component, by reading the real, currently-active
 * component context (which - unlike the plain `component`/`context`
 * variables `ui:ref` reads - is threaded correctly through slot-content nesting) and temporarily
 * re-exposing it as those ordinary variables.
 *
 * Intended for content whose real data doesn't exist yet at server-render time and is filled in
 * later, client-side (e.g. a combobox's async search results, file-upload item previews,
 * recurring/array form-field rows) - clone the `<template>`'s content, find its `ui:ref`'d
 * elements, and populate them directly.
 *
 * Also marks a `isRenderStencil` flag on the component context for the duration of rendering
 * children, so a nested component (e.g. `combobox.item`) can detect on its own that it's being
 * rendered as a client-filled stencil rather than a real instance, without the template author
 * having to pass an explicit prop for it.
 *
 * ## Example
 * ```html
 * <ui:combobox.root>
 *   ...
 *   <ui:combobox.content>
 *     <ui:template name="item-template" component="combobox">
 *         <ui:combobox.item>
 *             <span {ui:ref(name: 'title', withId: false)}></span>
 *         </ui:combobox.item>
 *     </ui:template>
 *   </ui:combobox.content>
 * </ui:combobox.root>
 * ```
 */
class TemplateViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('name', 'string', 'Ref name for the wrapping `<template>` element', true);
        $this->registerArgument(
            'component',
            'string',
            'Base name of the enclosing component this template belongs to, e.g. "combobox"',
            true,
        );
    }

    public function render(): string
    {
        $componentName = $this->arguments['component'];
        $context = ContextService::getFromRenderingContext($this->renderingContext, $componentName);

        if (!$context instanceof ComponentContextInterface) {
            throw new \RuntimeException(
                'ui:template could not find an active "' .
                $componentName .
                '" component to attach to. ' .
                'Make sure it is used inside a <ui:' .
                $componentName .
                '.root> (or similar).',
                1_767_900_100,
            );
        }

        $variableProvider = $this->renderingContext->getVariableProvider();

        $hadComponent = $variableProvider->exists('component');
        $previousComponent = $hadComponent ? $variableProvider->get('component') : null;
        $hadContext = $variableProvider->exists('context');
        $previousContext = $hadContext ? $variableProvider->get('context') : null;

        if ($hadComponent) {
            $variableProvider->remove('component');
        }
        $variableProvider->add('component', [
            'fullName' => $componentName . '.template',
            'baseName' => $componentName,
            'isRoot' => false,
            'isComposable' => true,
        ]);

        if ($hadContext) {
            $variableProvider->remove('context');
        }
        $variableProvider->add('context', $context);

        // Marks the context as "rendering a client-filled stencil" for the duration of rendering
        // our children, so a nested component (e.g. combobox.item/.itemText/.itemIndicator) can
        // detect this automatically via `context.isRenderStencil`, instead of requiring an
        // explicit prop from the template author. Saved/restored like component/context above,
        // for correct behavior if ui:template is ever nested.
        $wasRenderStencil = $context->get('isRenderStencil');
        $context->set('isRenderStencil', true);

        try {
            $refAttributes = new TagAttributes([
                'id' => ComponentUtility::generatePartId(
                    $componentName,
                    (string)$context->get('rootId'),
                    $this->arguments['name'],
                ),
                'data-scope' => $componentName,
                'data-part' => $this->arguments['name'],
            ]);

            return '<template ' . $refAttributes . '>' . $this->renderChildren() . '</template>';
        } finally {
            $context->set('isRenderStencil', $wasRenderStencil);

            $variableProvider->remove('component');
            if ($hadComponent) {
                $variableProvider->add('component', $previousComponent);
            }

            $variableProvider->remove('context');
            if ($hadContext) {
                $variableProvider->add('context', $previousContext);
            }
        }
    }
}
