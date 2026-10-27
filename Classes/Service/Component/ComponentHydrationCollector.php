<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Service\Component;

use Jramke\FluidPrimitives\Constants;
use Jramke\FluidPrimitives\Contexts\AbstractComponentContext;
use Jramke\FluidPrimitives\Domain\Dto\ComponentHydrationCandidate;
use Jramke\FluidPrimitives\Domain\Dto\HydrationEntry;
use Jramke\FluidPrimitives\Exception\HydrationException;
use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Registry\NestedComponentRegistry;
use Jramke\FluidPrimitives\Registry\ReferencedRootRegistry;
use Jramke\FluidPrimitives\Utility\ClientPropsContextExtractor;
use Jramke\FluidPrimitives\Utility\ComponentUtility;
use Jramke\FluidPrimitives\Utility\Typed;

/**
 * Registers a rendered root component's client-facing props for hydration, once it was actually
 * referenced client-side - `ui:ref`/`ui:template` record that in {@see ReferencedRootRegistry} (so it
 * holds however the ref'd markup ends up rendered, portaled away included), mirrored by
 * `ui:exposeToClient`'s marker for components with no ref'd part at all.
 *
 * Also the single place every such registration is recorded as nested inside the `ui:template` stencil
 * this render happens to be in (via {@see NestedComponentRegistry}'s tracking-scope stack), so this
 * works for *any* root component regardless of how it renders (e.g. `Dialog`, whose own `Root` renders
 * no DOM element at all), not just ones a client-side scan of the rendered HTML would happen to find.
 */
final readonly class ComponentHydrationCollector
{
    public function __construct(
        private ClientPropValueResolver $clientPropValueResolver,
    ) {}

    public function collectForRootComponent(ComponentHydrationCandidate $candidate): string
    {
        $rendered = $candidate->rendered;

        $rootId = ComponentUtility::getRootIdFromContext($candidate->renderingContext);
        if ($rootId === '' || $rootId === '0') {
            throw new HydrationException(
                'No rootId found for root component ' . $candidate->viewHelperName . '.',
                1756025241,
            );
        }

        // only register the components props for hydration if the user used the ui:ref viewhelper
        $hasRef = ReferencedRootRegistry::isReferenced($candidate->clientBaseName, $rootId);

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
            // A client-marked prop's resolved value is inherently mixed - it can be any PHP type a
            // `ui:prop` declares.
            // @mago-expect analysis:mixed-assignment
            $value = $candidate->ctx?->get($name) ?? $arguments[$name] ?? $argumentDefinition?->getDefaultValue();

            $this->clientPropValueResolver->assertRequiredPropNotNull(
                $value,
                $name,
                $candidate->clientBaseName,
                $argumentDefinition,
            );

            $propsMarkedForClientValues[$name] = $this->clientPropValueResolver->resolveClientValue(
                $value,
                $name,
                $candidate->clientBaseName,
                $argumentDefinition,
            );
        }

        // we dont want to send null values to the client, defaults should be defined in the component ts file
        $propsMarkedForClientValues = array_filter($propsMarkedForClientValues, static fn($value) => !is_null($value));

        $clientPropsFromContext = $candidate->ctx instanceof AbstractComponentContext
            ? ClientPropsContextExtractor::extract($candidate->ctx)
            : [];
        $exposedByContext = $candidate->ctx instanceof AbstractComponentContext
            ? ClientPropsContextExtractor::discover($candidate->ctx::class)
            : [];

        // A prop the context exposes is the context's to send: an `excludeIfNull` getter that returns null
        // means "nothing", not "send the raw argument", which could be the very value it rejected (`''`).
        $props = [...array_diff_key($propsMarkedForClientValues, $exposedByContext), ...$clientPropsFromContext];
        unset($props['id']); // Remove potential id from client props as it is handled separately
        unset($props['ids']);

        // Enforced here, lazily, rather than unconditionally for every root component in
        // ComponentIdentityResolver: a purely server-rendered root component with no ui:ref/exposed
        // prop at all never reaches this point (see the early return above) and so never needs its
        // collection globally registered just to render - only a component that's actually about
        // to be hydrated does.
        $namespaceIdentifier = $candidate->namespaceIdentifier ?? throw new HydrationException(
            sprintf(
                'Cannot hydrate "%s": its component collection is not registered under any Fluid ' .
                'namespace via $GLOBALS[\'TYPO3_CONF_VARS\'][\'SYS\'][\'fluid\'][\'namespaces\'] - a ' .
                'component collection must be globally registered for its components to be hydratable.',
                $candidate->clientBaseName,
            ),
            3552683359,
        );

        // Recorded before add() - add() self-triggers the inline hydration script's rebuild, and
        // this way that rebuild already reflects this component's own nested-tracking entry too,
        // rather than needing a second component's registration to come along and catch it up.
        // NestedComponentRegistry keeps a single combined "namespace:baseName" string (built here,
        // not restructured into HydrationRegistry's own per-namespace nesting) - its client-side
        // consumers already treat this value as an opaque round-tripped identifier, never index
        // into it as an object path, so there's no benefit to nesting it there too.
        NestedComponentRegistry::getInstance()->recordNestedComponent(
            "{$namespaceIdentifier}:{$candidate->clientBaseName}",
            $rootId,
        );
        $registry = HydrationRegistry::getInstance();
        $registry->initializeGlobals($candidate->renderingContext);
        $registry->add(
            $namespaceIdentifier,
            $candidate->clientBaseName,
            new HydrationEntry(
                $rootId,
                Typed::arrayOrNull($arguments['ids'] ?? null) ?? [],
                Typed::bool($arguments['autoMount'] ?? null, true),
                $props,
            ),
        );

        return $rendered;
    }
}
