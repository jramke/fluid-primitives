<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Domain\Dto;

/**
 * Result of {@see ComponentIdentityResolver::resolve()}.
 *
 * Reaches the rest of the rendering pipeline through two deliberately separate channels:
 * {@see forView()} exposes the small, genuinely public subset as the `component` Fluid variable
 * (readable by template authors via `{component.fullName}` etc.), while the full object - including
 * `$isDeclaredRoot`/`$isRenderedAsRoot`, which are internal bookkeeping only - is stored under
 * {@see VHVC_KEY} in the `ViewHelperVariableContainer`, unreachable from a template expression.
 */
final readonly class ComponentIdentity
{
    /**
     * The {@see \TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperVariableContainer} key this gets stored
     * under (keyed by this class's own name as the container's namespace) - see
     * {@see \Jramke\FluidPrimitives\Component\ComponentRenderer::createView()} for where it's
     * written and {@see \Jramke\FluidPrimitives\Utility\ComponentRootUtility} for where it's read
     * back.
     */
    public const string VHVC_KEY = 'current';

    public function __construct(
        /**
         * This specific call's *rendered-as* root identity: the declared fact (see
         * `$isDeclaredRoot`) adjusted for `spreadProps` delegation - false whenever this call
         * forwards its hydration/context identity to an ancestor instead of owning it. Drives
         * {@see \Jramke\FluidPrimitives\Component\ComponentRenderer}'s render-time bookkeeping
         * (context creation, lifecycle hooks, hydration collection) and is read back via
         * {@see \Jramke\FluidPrimitives\Utility\ComponentRootUtility::isRenderedAsRootFromContext()}.
         */
        public bool $isRenderedAsRoot,
        /**
         * Whether $viewHelperName's own declared shape (folder-shape default included) is root -
         * independent of `$isRenderedAsRoot`'s `spreadProps` adjustment for this specific call site.
         * A thin `ui:useProps` + `spreadProps` wrapper forwards its own *rendered-as* identity to
         * whichever ancestor it delegates to (that's what `$isRenderedAsRoot` tracks), but the
         * template it forwards *into* still declares its own `client`/`context` props and reads its
         * own `rootId` as if root - this is the fact those need, read back via
         * {@see \Jramke\FluidPrimitives\Utility\ComponentRootUtility::isDeclaredRootFromContext()}.
         */
        public bool $isDeclaredRoot,
        public ?string $rootId,
        /**
         * The canonical, as-authored component name (e.g. "fileUpload") - used for context
         * storage/lookup, the `component.baseName` Fluid variable, and any same-component-type
         * comparison. Not kebab-case; see `$clientBaseName` for that.
         */
        public string $baseName,
        /**
         * `$baseName`, kebab-cased (e.g. "file-upload") - for the few things that genuinely need
         * it: `data-scope` and {@see \Jramke\FluidPrimitives\Utility\ComponentPartIdUtility}'s
         * override maps. DOM-facing identity stays namespace-agnostic by design - see
         * `$namespaceIdentifier` for the hydration registry key instead.
         */
        public string $clientBaseName,
        /**
         * The Fluid namespace identifier (e.g. "ui"/"primitives") the collection that resolved this
         * component is registered under - `null` when it isn't registered globally, or for a
         * non-root candidate (never resolved, never needed). Combined with `$clientBaseName`, this
         * is the hydration registry key a root component would be recorded under - see
         * {@see HydrationRegistry::add()}. Being null here does *not* itself throw: only
         * {@see ComponentHydrationCollector::collectForRootComponent()} enforces it, lazily, and
         * only for a root component that actually turns out to need hydration - a purely
         * server-rendered one with no `ui:ref`/exposed prop never needs its collection globally
         * registered just to render.
         */
        public ?string $namespaceIdentifier,
    ) {}

    /**
     * The small, genuinely public-facing subset of this identity exposed to templates as the
     * `component` Fluid variable - deliberately excludes `$isDeclaredRoot`/`$isRenderedAsRoot` (see
     * this class's own docblock) and `$rootId`/`$namespaceIdentifier` (never needed by a template
     * author; `$rootId` already has its own dedicated `rootId` Fluid variable). `$viewHelperName` is
     * threaded in rather than stored on this DTO - it's only ever needed here, at the one call site
     * that already has it in scope, so there's no reason to carry it as a permanent field.
     *
     * @return array{fullName: string, baseName: string, clientBaseName: string}
     */
    public function forView(string $viewHelperName): array
    {
        return [
            'fullName' => $viewHelperName,
            'baseName' => $this->baseName,
            'clientBaseName' => $this->clientBaseName,
        ];
    }
}
