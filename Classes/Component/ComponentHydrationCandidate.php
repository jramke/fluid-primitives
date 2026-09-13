<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Component;

use Jramke\FluidPrimitives\Contexts\AbstractComponentContext;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\ArgumentDefinition;

/**
 * Everything {@see ComponentHydrationCollector} needs to decide whether a just-rendered root
 * component should be registered for client hydration, and what to register.
 */
final readonly class ComponentHydrationCandidate
{
    /**
     * @param array<string, mixed> $arguments
     * @param array<string, ArgumentDefinition> $argumentDefinitions
     * @param array<string, true> $propsMarkedForClient
     * @param array<string, string[]> $portalRegistrySnapshotBeforeRender
     */
    public function __construct(
        public string $rendered,
        public string $viewHelperName,
        public RenderingContextInterface $renderingContext,
        public string $baseName,
        public array $arguments,
        public array $argumentDefinitions,
        public array $propsMarkedForClient,
        public ?AbstractComponentContext $ctx,
        public ?string $fieldRootId,
        public ?string $checkboxGroupRootId,
        public array $portalRegistrySnapshotBeforeRender,
    ) {}
}
