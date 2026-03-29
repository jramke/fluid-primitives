<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Service;

use Jramke\FluidPrimitives\Contexts\ComponentContextInterface;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * Service for managing component contexts in a stack-based approach.
 *
 * This allows nested components of the same type (e.g., nested dialogs)
 * to each have their own context without overwriting each other.
 * When a nested component finishes rendering, its context is popped
 * from the stack, restoring the parent's context.
 */
class ContextService
{
    /**
     * Get the current (topmost) context for a component type.
     */
    public static function getFromRenderingContext(
        RenderingContextInterface $renderingContext,
        string $name,
    ): ?ComponentContextInterface {
        $variableContainer = $renderingContext->getViewHelperVariableContainer();
        $stack = $variableContainer->get(self::class, $name);

        if (!is_array($stack) || empty($stack)) {
            return null;
        }

        // Return the topmost context (last element in the stack)
        return end($stack) ?: null;
    }

    /**
     * Get all component contexts (returns the topmost context for each component type).
     */
    public static function getAllFromRenderingContext(RenderingContextInterface $renderingContext): array
    {
        $variableContainer = $renderingContext->getViewHelperVariableContainer();
        $allStacks = $variableContainer->getAll(self::class);

        $result = [];
        foreach ($allStacks as $name => $stack) {
            if (is_array($stack) && !empty($stack)) {
                $result[$name] = end($stack);
            }
        }

        return $result;
    }

    /**
     * Push a context onto the stack for a component type.
     */
    public static function addToRenderingContext(
        RenderingContextInterface $renderingContext,
        string $name,
        ComponentContextInterface $context,
    ): void {
        $variableContainer = $renderingContext->getViewHelperVariableContainer();
        $stack = $variableContainer->get(self::class, $name);

        if (!is_array($stack)) {
            $stack = [];
        }

        // Push the new context onto the stack
        $stack[] = $context;
        $variableContainer->addOrUpdate(self::class, $name, $stack);
    }

    /**
     * @param RenderingContextInterface $renderingContext
     * @param array<string, ComponentContextInterface> $contexts
     */
    public static function addAllToRenderingContext(RenderingContextInterface $renderingContext, array $contexts): void
    {
        foreach ($contexts as $name => $context) {
            self::addToRenderingContext($renderingContext, $name, $context);
        }
    }

    /**
     * Pop the topmost context from the stack for a component type.
     * This restores the parent's context if there was one.
     */
    public static function removeFromRenderingContext(RenderingContextInterface $renderingContext, string $name): void
    {
        $variableContainer = $renderingContext->getViewHelperVariableContainer();

        if (!$variableContainer->exists(self::class, $name)) {
            return;
        }

        $stack = $variableContainer->get(self::class, $name);

        if (!is_array($stack) || empty($stack)) {
            $variableContainer->remove(self::class, $name);
            return;
        }

        // Pop the topmost context
        array_pop($stack);

        if (empty($stack)) {
            // No more contexts, remove the key entirely
            $variableContainer->remove(self::class, $name);
        } else {
            // Update with remaining stack
            $variableContainer->addOrUpdate(self::class, $name, $stack);
        }
    }
}
