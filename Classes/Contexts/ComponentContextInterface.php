<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Psr\Container\ContainerInterface;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * Interface for the component contexts.
 */
interface ComponentContextInterface extends ContainerInterface
{
    public function initialize(
        RenderingContextInterface $renderingContext,
        RenderingContextInterface $parentRenderingContext,
        array $contextVariables = [],
    ): void;

    /**
     * Gets a context variable by its key.
     */
    public function get(string $key): mixed;

    /**
     * Sets a context variable by its key.
     */
    public function set(string $key, mixed $value): void;

    /**
     * Checks if a context variable exists.
     */
    public function has(string $key): bool;

    /**
     * Gets all context variables as an associative array.
     */
    public function getAllVariables(): array;

    /**
     * Gets the rendering context associated with this component context.
     */
    public function getRenderingContext(): RenderingContextInterface;

    /**
     * Gets the parent rendering context associated with this component context.
     */
    public function getParentRenderingContext(): ?RenderingContextInterface;

    /** 
     * Lifecycle method called before rendering. Only called for root or closed components.
     * When modifying the ParentRenderingContext here, make sure to clean it up in afterRendering().
     */
    public function beforeRendering(): void;

    /** 
     * Lifecycle method called after rendering. Only called for root or closed components.
     */
    public function afterRendering(string &$html): void;
}
