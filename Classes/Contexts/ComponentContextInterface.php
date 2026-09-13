<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Component\ComponentCollectionInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * Interface for the component contexts.
 */
// This is the public contract every .fluid.html template's `context.X` access relies on across all
// Context subclasses; the method count is the interface, not internal complexity to delegate elsewhere.
// @mago-expect lint:too-many-methods
interface ComponentContextInterface extends ContainerInterface
{
    public function initialize(
        RenderingContextInterface $renderingContext,
        RenderingContextInterface $parentRenderingContext,
        ComponentCollectionInterface $componentResolver,
        array $contextVariables = [],
    ): void;

    /**
     * Gets a context variable by its key.
     */
    // get()/has() extend ContainerInterface only to gain its array-access-like get/has shape for
    // Fluid's context.* template lookups - $key (matching set()'s own parameter and every docblock
    // below) is more meaningful here than ContainerInterface's generic DI-container $id, and nothing
    // in this codebase calls these with named arguments expecting PSR container semantics.
    // @mago-expect analysis:incompatible-parameter-name
    public function get(string $key): mixed;

    /**
     * Sets a context variable by its key.
     */
    public function set(string $key, mixed $value): void;

    /**
     * Checks if a context variable exists.
     */
    // @mago-expect analysis:incompatible-parameter-name
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
     * Gets the component resolver associated with this component context.
     */
    public function getComponentResolver(): ComponentCollectionInterface;

    /**
     * Gets the current HTTP request.
     */
    public function getRequest(): ServerRequestInterface;

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
