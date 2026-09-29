<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Service\Component;

use Jramke\FluidPrimitives\Component\ComponentCollectionInterface;
use Jramke\FluidPrimitives\Domain\Dto\ComponentIdentity;
use Jramke\FluidPrimitives\Service\ComponentCollectionService;
use Jramke\FluidPrimitives\Utility\ComponentNameUtility;
use Jramke\FluidPrimitives\Utility\ComponentUtility;
use Jramke\FluidPrimitives\Utility\Typed;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * Resolves a component call's basic identity from its ViewHelper name and arguments: whether it's a
 * root or composable (subcomponent) component, its rootId, its base name (e.g. "accordion" for both
 * "Accordion.Root" and "Accordion.Item"), and - for a root component - the Fluid namespace identifier
 * of the collection that resolved it.
 */
final readonly class ComponentIdentityResolver
{
    public function __construct(
        private ComponentCollectionService $componentCollectionService,
    ) {}

    /**
     * @param array<string, mixed> $arguments
     */
    public function resolve(
        string $viewHelperName,
        array $arguments,
        RenderingContextInterface $renderingContext,
        ComponentCollectionInterface $componentResolver,
    ): ComponentIdentity {
        // Reads the fact already decided once at template-compile time (including the
        // filesystem-aware folder-shape default) back from AbstractComponentCollection's own
        // per-request cache, rather than re-deriving it here on every render.
        $isDeclaredRoot = $componentResolver->isDeclaredRoot($viewHelperName);

        // $isRenderedAsRoot tracks this specific call's *rendered-as* identity for hydration/context
        // bookkeeping - $isDeclaredRoot (unadjusted) is what the rendered template itself still
        // needs, see that property's own docblock on ComponentIdentity.
        $isRenderedAsRoot = $isDeclaredRoot;
        $spreadProps = Typed::arrayOrNull($arguments['spreadProps'] ?? null);
        if ($spreadProps !== null && $spreadProps !== []) {
            $isRenderedAsRoot = false;
        }

        $rootId = Typed::stringOrNull($arguments['rootId'] ?? null);
        if ($rootId !== null && preg_match('/["\\\\]/', $rootId) === 1) {
            // The rootId is the value of every part attribute and lands unescaped in zag's own attribute selectors.
            throw new \InvalidArgumentException(
                sprintf('The rootId "%s" must not contain quotes or backslashes.', $rootId),
                1_788_200_001,
            );
        }
        if ($rootId === null) {
            // For a non-root component we assign the rootId of the parent component when rendering subcomponents.
            $rootId = $isRenderedAsRoot
                ? ComponentUtility::id()
                : Typed::stringOrNull($renderingContext->getVariableProvider()->get('rootId'));
        }

        $baseName = ComponentNameUtility::getComponentBaseNameFromViewHelperName($viewHelperName, $isDeclaredRoot);
        $clientBaseName = ComponentNameUtility::camelCaseToLowerCaseDashed($baseName);

        // $componentResolver->getNamespace() is AbstractComponentCollection's own PHP class name (a
        // naming trap shared with Fluid core's ViewHelperResolverDelegateInterface), not the Fluid
        // tag-prefix identifier ("ui"/"primitives") - this reverse lookup is what actually recovers
        // that. Resolved unconditionally (memoized, so cheap) rather than gated behind
        // $isRenderedAsRoot alone - whether it's actually *required* (a root component this render
        // turns out to need hydration for) is enforced lazily, in
        // {@see ComponentHydrationCollector::collectForRootComponent()}, not here: a purely
        // server-rendered root component with no `ui:ref`/exposed prop at all never needs one, and
        // shouldn't be forced to have its collection globally registered just to render.
        $namespaceIdentifier = $isRenderedAsRoot
            ? $this->componentCollectionService->getViewHelperNamespaceIdentifierByCollectionClassName(
                $componentResolver->getNamespace(),
            )
            : null;

        return new ComponentIdentity(
            $isRenderedAsRoot,
            $isDeclaredRoot,
            $rootId,
            $baseName,
            $clientBaseName,
            $namespaceIdentifier,
        );
    }
}
