<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Service\Component;

use Jramke\FluidPrimitives\Constants;
use Jramke\FluidPrimitives\Contexts\AbstractComponentContext;
use Jramke\FluidPrimitives\Contexts\ComponentContextInterface;
use Jramke\FluidPrimitives\Domain\Dto\ComponentHydrationCandidate;
use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Registry\NestedComponentRegistry;
use Jramke\FluidPrimitives\Registry\PortalRegistry;
use Jramke\FluidPrimitives\Service\ContextService;
use Jramke\FluidPrimitives\Utility\ClientPropsContextExtractor;
use Jramke\FluidPrimitives\Utility\ComponentNameUtility;
use Jramke\FluidPrimitives\Utility\ComponentPartIdUtility;
use Jramke\FluidPrimitives\Utility\ComponentUtility;
use Jramke\FluidPrimitives\Utility\Typed;

/**
 * Registers a rendered root component's client-facing props for hydration, once its output (or
 * whatever it portaled away, via {@see PortalRegistry}) shows it was actually referenced client-side -
 * `ui:ref` always emits `data-scope="{componentName}"`, which is the detection signal this looks for,
 * mirrored by `ui:exposeToClient`'s marker for components with no ref'd part at all.
 *
 * Also the single place every such registration is recorded against whatever
 * {@see NestedComponentRegistry::recordNestedComponent()} tracking is currently active for it - a
 * `ui:template` stencil this render happens to be nested inside (via the tracking-scope stack), or
 * a real, server-rendered `FieldArray` row (via {@see resolveFieldArrayItemScopeKey}, since a real
 * row has no `ui:template` wrapping it to push/pop a scope) - so this works for *any* root component
 * regardless of how it renders (e.g. `Dialog`, whose own `Root` renders no DOM element at all), not
 * just ones a client-side scan of the rendered HTML would happen to find.
 */
final readonly class ComponentHydrationCollector
{
    public function collectForRootComponent(ComponentHydrationCandidate $candidate): string
    {
        $rendered = $candidate->rendered;

        $rootId = ComponentUtility::getRootIdFromContext($candidate->renderingContext);
        if ($rootId === '' || $rootId === '0') {
            throw new \RuntimeException(
                'No rootId found for root component ' . $candidate->viewHelperName . '.',
                1756025241,
            );
        }

        // only register the components props for hydration if the user used the ui:ref viewhelper
        // ui:ref always emits data-scope="{clientBaseName}", so that's our detection signal
        $clientBaseName = ComponentNameUtility::getClientBaseNameFromContext($candidate->renderingContext);
        $newlyPortaledHtml = $this->extractNewlyPortaledHtml(
            $candidate->portalRegistrySnapshotBeforeRender,
            PortalRegistry::getInstance()->getAll(),
        );
        $hasRef =
            str_contains($rendered, 'data-scope="' . $clientBaseName . '"') ||
            str_contains($newlyPortaledHtml, 'data-scope="' . $clientBaseName . '"');

        $manuallyExposedToClient = str_contains($rendered, Constants::MANUALLY_EXPOSED_TO_CLIENT_MARKER);

        $hasPropsMarkedForClient = count($candidate->propsMarkedForClient) > 0;

        if (!$hasRef && !$manuallyExposedToClient && !$hasPropsMarkedForClient) {
            return $rendered;
        }

        if ($manuallyExposedToClient) {
            $rendered = str_replace(Constants::MANUALLY_EXPOSED_TO_CLIENT_MARKER, replace: '', subject: $rendered);
        }

        $arguments = $candidate->arguments;
        $argumentDefinitions = $candidate->argumentDefinitions;

        $propsMarkedForClientValues = [];
        foreach (array_keys($candidate->propsMarkedForClient) as $name) {
            // Reads from the context first, not the raw argument - a context's own
            // beforeRendering() may have rewritten this prop after the argument was already frozen
            // at the tag's own call site (e.g. FieldContext prefixing `name` with its enclosing
            // FieldArray's own name/index, or auto-resolving `defaultValue` from a bound object).
            // ComponentRootContextFactory::buildContextVariables() seeds the context with every
            // argument's own processed value at construction, so this is a strict superset of
            // reading the argument directly, not a narrower substitute for it - correct for every
            // other context too, which never touches these variables and so just gets the same
            // value back. `$candidate->ctx` is null only for a root component with no context at
            // all, hence the fallback to the argument/its definition's default.
            $argumentDefinition = $argumentDefinitions[$name] ?? null;
            $propsMarkedForClientValues[$name] =
                $candidate->ctx?->get($name) ?? $arguments[$name] ?? $argumentDefinition?->getDefaultValue();
        }

        // we dont want to send null values to the client, defaults should be defined in the component ts file
        $propsMarkedForClientValues = array_filter($propsMarkedForClientValues, static fn($value) => !is_null($value));

        $clientPropsFromContext = $candidate->ctx instanceof AbstractComponentContext
            ? ClientPropsContextExtractor::extract($candidate->ctx)
            : [];

        $props = [...$propsMarkedForClientValues, ...$clientPropsFromContext];
        unset($props['id']); // Remove potential id from client props as it is handled separately
        unset($props['ids']);

        $data = [
            'controlled' => $arguments['controlled'] ?? false,
            'props' => [
                'id' => $rootId,
                'ids' => $arguments['ids'] ?? [],
                ...$props,
            ],
            ...array_filter($candidate->relatedContextRootIds),
        ];

        // Recorded before add() - add() self-triggers the inline hydration script's rebuild, and
        // this way that rebuild already reflects this component's own nested-tracking entry too,
        // rather than needing a second component's registration to come along and catch it up.
        NestedComponentRegistry::getInstance()->recordNestedComponent(
            $candidate->clientBaseName,
            $rootId,
            $this->resolveFieldArrayItemScopeKey($candidate),
        );
        HydrationRegistry::getInstance()->add($candidate->clientBaseName, $rootId, $data);

        return $rendered;
    }

    /**
     * The row-scoped tracking key a real, server-rendered `FieldArray` row's own nested components
     * (e.g. a `Field`+`Input`) should *also* be recorded against, alongside whatever `ui:template`
     * scope is on the stack (there is none here - a real row is an ordinary `f:for` iteration, not
     * `ui:template` content) - `null` when this component isn't rendering inside a `FieldArray` item
     * at all, or is rendering inside `itemTemplate`'s own unfilled stencil (`item.index` is null
     * there; that case is already covered by the tracking-scope stack instead, via the stencil's own
     * id), the common case for every other root component.
     *
     * Reads `$candidate->ctx`'s own `getParentRenderingContext()` - the same ambient-context lookup
     * `FieldContext::beforeRendering()` already performs to prefix a nested field's own `name` - not
     * `$candidate->renderingContext` itself, which is this *component's own*, freshly reset rendering
     * context (see `ComponentRenderer::renderComponent()`), not the one carrying the `fieldArray`
     * context on its `ContextService` stack.
     *
     * Uses `ComponentPartIdUtility::generatePartId()` (the same formula `ui:ref` itself uses for
     * `fieldArray.item`'s own `{ui:ref(name: 'item', value: index)}`) rather than building the id by
     * hand, so this can never drift out of sync with what the row's own DOM id - and therefore what
     * `ComponentHydrator.renameValue()` looks this key up by client-side - actually is.
     */
    private function resolveFieldArrayItemScopeKey(ComponentHydrationCandidate $candidate): ?string
    {
        if (!$candidate->ctx instanceof AbstractComponentContext) {
            return null;
        }

        $fieldArrayContext = ContextService::getFromRenderingContext(
            $candidate->ctx->getParentRenderingContext(),
            'fieldArray',
        );
        if (!$fieldArrayContext instanceof ComponentContextInterface) {
            return null;
        }

        $itemIndex = Typed::intOrNull($fieldArrayContext->get('item.index'));
        if ($itemIndex === null) {
            return null;
        }

        $fieldArrayRootId = Typed::stringOrNull($fieldArrayContext->get('rootId'));
        if ($fieldArrayRootId === null) {
            return null;
        }

        return ComponentPartIdUtility::generatePartId('field-array', $fieldArrayRootId, 'item', (string)$itemIndex);
    }

    /**
     * Concatenates whatever `PortalRegistry` entries were added between `$before` and `$after` -
     * i.e. everything `ui:portal` buffered away while rendering this component, across every named
     * portal bucket. Diffed by per-bucket length rather than array-diffed by value, since two
     * unrelated portaled fragments could legitimately render identical markup (e.g. two dialogs
     * with the same content) and would otherwise be indistinguishable/collapsed.
     *
     * @param array<string, string[]> $before
     * @param array<string, string[]> $after
     */
    private function extractNewlyPortaledHtml(array $before, array $after): string
    {
        $newlyPortaledHtml = '';
        foreach ($after as $name => $entries) {
            $previousCount = count($before[$name] ?? []);
            foreach (array_slice($entries, $previousCount) as $entry) {
                $newlyPortaledHtml .= $entry;
            }
        }

        return $newlyPortaledHtml;
    }
}
