<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Service;

use Jramke\FluidPrimitives\Contexts\ComponentContextInterface;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

class ContextService
{
    public static function getFromRenderingContext(RenderingContextInterface $renderingContext, string $name): ?ComponentContextInterface
    {
        $variableContainer = $renderingContext->getViewHelperVariableContainer();
        return $variableContainer->get(self::class, $name);
    }

    public static function getAllFromRenderingContext(RenderingContextInterface $renderingContext): array
    {
        $variableContainer = $renderingContext->getViewHelperVariableContainer();
        return $variableContainer->getAll(self::class);
    }

    public static function addToRenderingContext(RenderingContextInterface $renderingContext, string $name, ComponentContextInterface $context): void
    {
        $variableContainer = $renderingContext->getViewHelperVariableContainer();
        $variableContainer->add(self::class, $name, $context);
    }

    /**
     * @param RenderingContextInterface $renderingContext
     * @param array<string, ComponentContextInterface> $contexts
     */
    public static function addAllToRenderingContext(RenderingContextInterface $renderingContext, array $contexts): void
    {
        $variableContainer = $renderingContext->getViewHelperVariableContainer();
        foreach ($contexts as $name => $context) {
            $variableContainer->add(self::class, $name, $context);
        }
    }

    public static function removeFromRenderingContext(RenderingContextInterface $renderingContext, string $name): void
    {
        $variableContainer = $renderingContext->getViewHelperVariableContainer();
        if ($variableContainer->exists(self::class, $name)) {
            $variableContainer->remove(self::class, $name);
        }
    }
}
