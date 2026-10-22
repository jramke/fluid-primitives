<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\ViewHelpers;

use Jramke\FluidPrimitives\Contexts\ComponentContextInterface;
use Jramke\FluidPrimitives\Domain\Dto\ComponentIdentity;
use Jramke\FluidPrimitives\Domain\Dto\TagAttributes;
use Jramke\FluidPrimitives\Registry\NestedComponentRegistry;
use Jramke\FluidPrimitives\Registry\ReferencedRootRegistry;
use Jramke\FluidPrimitives\Service\ContextService;
use Jramke\FluidPrimitives\Utility\ComponentNameUtility;
use Jramke\FluidPrimitives\Utility\ComponentRefUtility;
use Jramke\FluidPrimitives\Utility\ComponentUtility;
use TYPO3Fluid\Fluid\Core\Variables\VariableProviderInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 *
 * Wraps markup in a `<template>` that the client clones for content the server does not have yet, such as async search results, previews of uploaded files or the rows of a recurring field.
 *
 * ```html
 * <ui:combobox.root>
 *   ...
 *   <ui:combobox.content>
 *     <ui:combobox.list>
 *       <ui:template name="itemTemplate" context="combobox">
 *           <ui:combobox.item>
 *               <span {ui:ref(name: 'title')}></span>
 *           </ui:combobox.item>
 *       </ui:template>
 *     </ui:combobox.list>
 *   </ui:combobox.content>
 * </ui:combobox.root>
 * ```
 *
 * Clone the template and fill its `ui:ref` parts with the [`Template`](/docs/client-utilities/template) client utility. Inside it, `ui:ref` works as in a component's own template, and components such as `ui:combobox.item` render as part of the template without extra props.
 *
 * `name` is the ref name of the `<template>` element: `itemTemplate` renders `data-combobox-item-template="{rootId}"`.
 *
 * ## Set the context
 *
 * `context` is the camelCase name of the enclosing component. Pass it when the template is slot content of another component, as above. That includes the plain Fluid template of a content element. Omit it in the template body of a component, where it defaults to the active component. A native `<template>` element is enough for a one-off client-rendered list that has no enclosing component.
 *
 */
class TemplateViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument(
            'name',
            'string',
            'Ref name for the wrapping `<template>` element, camelCase (e.g. "itemTemplate") like `ui:ref`\'s own `name` argument.',
            true,
        );
        $this->registerArgument(
            'context',
            'string',
            'camelCase base name of the enclosing component this template belongs to, e.g. "fileUpload". Required when this ui:template is slot content passed into another component or sits in a plain Fluid template. Defaults to the active component when written directly inside a component\'s own template body.',
            false,
            '',
        );
    }

    public function render(): string
    {
        [$baseName, $clientBaseName, $context] = $this->resolveContext();

        $renderingContext = $this->renderingContext ?? throw new \RuntimeException(
            'Template ViewHelper is missing its rendering context.',
            1_788_100_004,
        );
        // `component` is the same shape a real render's ComponentIdentity::forView() produces, built via
        // the shared ComponentIdentity::viewShape() instead of a hand-written array literal here -
        // deliberately no isDeclaredRoot/isRenderedAsRoot: those live on the
        // ViewHelperVariableContainer for a real component render (see
        // ComponentRenderer::createView()), not on this Fluid variable, and nothing that reads
        // them (ui:prop, ui:exposeToClient) is ever legitimately used from slot content in the
        // first place - so there's nothing to fake here. A `ui:ref` reads the bare `rootId` variable
        // when the template it sits in is itself a declared root component (e.g. a docs example
        // wrapping a combobox) - that variable is the *wrapper's* own id, not the enclosing
        // component's this stencil belongs to.
        $restoreVariables = $this->overrideVariables($renderingContext->getVariableProvider(), [
            'component' => ComponentIdentity::viewShape($baseName . '.template', $baseName, $clientBaseName),
            'context' => $context,
            'rootId' => (string)$context->get('rootId'),
        ]);

        // Marks the context as "rendering a client-filled stencil" for the duration of rendering
        // our children, so a nested component (e.g. combobox.item/.itemText/.itemIndicator) can
        // detect this automatically via `context.isRenderStencil`, instead of requiring an
        // explicit prop from the template author. Saved/restored like the variables above,
        // for correct behavior if ui:template is ever nested; same opaque-mixed reasoning applies.
        // @mago-expect analysis:mixed-assignment
        $wasRenderStencil = $context->get('isRenderStencil');
        $context->set('isRenderStencil', true);

        try {
            $part = (string)$this->arguments['name'];
            $rootId = (string)$context->get('rootId');
            $stencilKey = ComponentRefUtility::getScopeKey($clientBaseName, $rootId, $part);
            $refAttributes = new TagAttributes([
                ComponentRefUtility::getAttributeName($clientBaseName, $part) => $rootId,
            ]);
            ReferencedRootRegistry::mark($clientBaseName, $rootId);

            // Opens a tracking scope keyed by this stencil's own key, so every root component that
            // registers itself while rendering our children (however it renders - see
            // NestedComponentRegistry::recordNestedComponent()'s own docblock) is recorded as nested
            // inside this stencil. The client reads that list instead of inferring nesting from
            // rendered DOM shape (see ComponentHydrator.restampValue in Client/src/lib/hydration.ts).
            NestedComponentRegistry::getInstance()->pushTrackingScope($stencilKey);
            try {
                $renderedChildren = (string)$this->renderChildren();
            } finally {
                NestedComponentRegistry::getInstance()->popTrackingScope();
            }

            return '<template ' . (string)$refAttributes . '>' . $renderedChildren . '</template>';
        } finally {
            $context->set('isRenderStencil', $wasRenderStencil);
            $restoreVariables();
        }
    }

    /**
     * Replaces the given Fluid variables and returns a callback restoring exactly what was there
     * before - the previous values are whatever an earlier render put there, unknowable here, and
     * must stay opaque to be restored faithfully.
     *
     * @param array<string, mixed> $variables
     */
    private function overrideVariables(VariableProviderInterface $variableProvider, array $variables): \Closure
    {
        $previous = [];
        // Each variable is genuinely heterogeneous (an identity array, a context object, a string).
        // @mago-expect analysis:mixed-assignment
        foreach ($variables as $name => $value) {
            if ($variableProvider->exists($name)) {
                $previous[$name] = $variableProvider->get($name);
                $variableProvider->remove($name);
            }
            $variableProvider->add($name, $value);
        }

        return static function () use ($variableProvider, $variables, $previous): void {
            foreach (array_keys($variables) as $name) {
                $variableProvider->remove($name);
                if (array_key_exists($name, $previous)) {
                    $variableProvider->add($name, $previous[$name]);
                }
            }
        };
    }

    /**
     * @return array{0: string, 1: string, 2: ComponentContextInterface} [baseName, clientBaseName, context]
     */
    private function resolveContext(): array
    {
        $renderingContext = $this->renderingContext ?? throw new \RuntimeException(
            'Template ViewHelper is missing its rendering context.',
            1_788_100_005,
        );
        $explicitContextName = (string)($this->arguments['context'] ?? '');

        if ($explicitContextName !== '') {
            // ContextService is keyed by the component's canonical camelCase base name, so no
            // conversion happens on the lookup itself.
            return [
                $explicitContextName,
                ComponentNameUtility::camelCaseToLowerCaseDashed($explicitContextName),
                ContextService::requireFromRenderingContext($renderingContext, $explicitContextName, 'ui:template'),
            ];
        }

        $variableProvider = $renderingContext->getVariableProvider();
        // Narrowed immediately below via instanceof - there's no Typed:: equivalent for objects.
        // @mago-expect analysis:mixed-assignment
        $ambientContext = $variableProvider->exists('context') ? $variableProvider->get('context') : null;

        if (ComponentUtility::isComponent($renderingContext) && $ambientContext instanceof ComponentContextInterface) {
            return [
                ComponentNameUtility::getComponentBaseNameFromContext($renderingContext),
                ComponentNameUtility::getClientBaseNameFromContext($renderingContext),
                $ambientContext,
            ];
        }

        throw new \RuntimeException(
            'ui:template could not determine which component to attach to. Pass `context` explicitly ' .
            '(e.g. context="combobox") when this ui:template is slot content passed into another ' .
            'component, rather than written directly inside a component\'s own template body.',
            1_767_900_200,
        );
    }
}
