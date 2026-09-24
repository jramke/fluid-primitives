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
        $isRootComponent = ComponentNameUtility::isRootComponent($viewHelperName);
        if (($arguments['spreadProps'] ?? null) === true) {
            $isRootComponent = false;
        }

        $isComposableComponent = ComponentNameUtility::isComposableComponent($viewHelperName);

        $rootId = Typed::stringOrNull($arguments['rootId'] ?? null);
        if ($rootId === null) {
            // For a non-root component we assign the rootId of the parent component when rendering subcomponents.
            $rootId = $isRootComponent
                ? ComponentUtility::id()
                : Typed::stringOrNull($renderingContext->getVariableProvider()->get('rootId'));
        }

        $baseName = ComponentNameUtility::getComponentBaseNameFromViewHelperName($viewHelperName);
        $clientBaseName = ComponentNameUtility::camelCaseToLowerCaseDashed($baseName);

        // $componentResolver->getNamespace() is AbstractComponentCollection's own PHP class name (a
        // naming trap shared with Fluid core's ViewHelperResolverDelegateInterface), not the Fluid
        // tag-prefix identifier ("ui"/"primitives") - this reverse lookup is what actually recovers
        // that. Resolved unconditionally (memoized, so cheap) rather than gated behind
        // $isRootComponent alone - whether it's actually *required* (a root component this render
        // turns out to need hydration for) is enforced lazily, in
        // {@see ComponentHydrationCollector::collectForRootComponent()}, not here: a purely
        // server-rendered root component with no `ui:ref`/exposed prop at all never needs one, and
        // shouldn't be forced to have its collection globally registered just to render.
        $namespaceIdentifier = $isRootComponent
            ? $this->componentCollectionService->getViewHelperNamespaceIdentifierByCollectionClassName(
                $componentResolver->getNamespace(),
            )
            : null;

        return new ComponentIdentity(
            $isRootComponent,
            $isComposableComponent,
            $rootId,
            $baseName,
            $clientBaseName,
            $namespaceIdentifier,
        );
    }
}
