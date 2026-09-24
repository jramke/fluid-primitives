<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Domain\Dto;

/**
 * Result of {@see ComponentIdentityResolver::resolve()}.
 */
final readonly class ComponentIdentity
{
    public function __construct(
        public bool $isRootComponent,
        public bool $isComposableComponent,
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
}
