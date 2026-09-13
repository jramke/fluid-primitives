<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Component;

use Jramke\FluidPrimitives\Constants;
use Jramke\FluidPrimitives\Contexts\AbstractComponentContext;
use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Registry\PortalRegistry;
use Jramke\FluidPrimitives\Utility\ClientPropsContextExtractor;
use Jramke\FluidPrimitives\Utility\ComponentUtility;

/**
 * Registers a rendered root component's client-facing props for hydration, once its output (or
 * whatever it portaled away, via {@see PortalRegistry}) shows it was actually referenced client-side -
 * `ui:ref` always emits `data-scope="{componentName}"`, which is the detection signal this looks for,
 * mirrored by `ui:exposeToClient`'s marker for components with no ref'd part at all.
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
        // ui:ref always emits data-scope="{componentName}", so that's our detection signal
        $componentBaseName = ComponentUtility::getComponentBaseNameFromContext($candidate->renderingContext);
        $newlyPortaledHtml = $this->extractNewlyPortaledHtml(
            $candidate->portalRegistrySnapshotBeforeRender,
            PortalRegistry::getAll(),
        );
        $hasRef =
            str_contains($rendered, 'data-scope="' . $componentBaseName . '"') ||
            str_contains($newlyPortaledHtml, 'data-scope="' . $componentBaseName . '"');

        $manuallyExposedToClient = str_contains($rendered, Constants::MANUALLY_EXPOSED_TO_CLIENT_MARKER);

        if (!$hasRef && !$manuallyExposedToClient) {
            return $rendered;
        }

        if ($manuallyExposedToClient) {
            $rendered = str_replace(Constants::MANUALLY_EXPOSED_TO_CLIENT_MARKER, '', $rendered);
        }

        $arguments = $candidate->arguments;
        $argumentDefinitions = $candidate->argumentDefinitions;

        $propsMarkedForClientValues = [];
        foreach (array_keys($candidate->propsMarkedForClient) as $name) {
            if (!isset($arguments[$name]) && !isset($argumentDefinitions[$name])) {
                continue;
            }

            $propsMarkedForClientValues[$name] = $arguments[$name] ?? $argumentDefinitions[$name]->getDefaultValue() ?? null;
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
        ];
        if ($candidate->fieldRootId) {
            $data['field'] = $candidate->fieldRootId;
        }
        if ($candidate->checkboxGroupRootId) {
            $data['checkboxGroup'] = $candidate->checkboxGroupRootId;
        }

        HydrationRegistry::getInstance()->add($candidate->baseName, $rootId, $data);

        return $rendered;
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
