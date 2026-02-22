<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Factory;

use Jramke\FluidPrimitives\Component\ComponentCollectionInterface;
use Jramke\FluidPrimitives\Contexts\ComponentContextInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

final class ComponentContextFactory
{
    /**
     * Create a component context instance with proper dependency injection
     *
     * @param class-string<ComponentContextInterface> $contextClassName
     * @param RenderingContextInterface $renderingContext
     * @param RenderingContextInterface $parentRenderingContext
     * @param ComponentCollectionInterface $componentResolver
     * @param array $contextVariables
     * @return ComponentContextInterface
     */
    public function create(
        string $contextClassName,
        RenderingContextInterface $renderingContext,
        RenderingContextInterface $parentRenderingContext,
        ComponentCollectionInterface $componentResolver,
        array $contextVariables = [],
    ): ComponentContextInterface {
        /** @var ComponentContextInterface $context */
        $context = GeneralUtility::makeInstance($contextClassName);

        // Initialize with state
        $context->initialize($renderingContext, $parentRenderingContext, $componentResolver, $contextVariables);

        return $context;
    }
}
