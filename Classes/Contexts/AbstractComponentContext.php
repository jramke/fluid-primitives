<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use ArrayAccess;
use Jramke\FluidPrimitives\Component\ComponentCollectionInterface;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

abstract class AbstractComponentContext implements ComponentContextInterface, \ArrayAccess
{
    private RenderingContextInterface $renderingContext;
    private RenderingContextInterface $parentRenderingContext;
    private array $contextVariables = [];
    private ComponentCollectionInterface $componentResolver;

    /**
     * Initialize the context with state after dependency injection
     */
    public function initialize(
        RenderingContextInterface $renderingContext,
        RenderingContextInterface $parentRenderingContext,
        ComponentCollectionInterface $componentResolver,
        array $contextVariables = [],
    ): void {
        $this->renderingContext = $renderingContext;
        $this->parentRenderingContext = $parentRenderingContext;
        $this->componentResolver = $componentResolver;
        $this->contextVariables = $contextVariables;
    }

    public function getRenderingContext(): RenderingContextInterface
    {
        return $this->renderingContext;
    }

    public function getParentRenderingContext(): RenderingContextInterface
    {
        return $this->parentRenderingContext;
    }

    public function getComponentResolver(): ComponentCollectionInterface
    {
        return $this->componentResolver;
    }

    public function getAllVariables(): array
    {
        return $this->contextVariables;
    }

    /**
     * Get a context variable by key, supports dot notation for nested access.
     * Example: $this->get('scrollbar.orientation')
     */
    public function get(string $key): mixed
    {
        // Direct key lookup first (faster for non-nested access)
        if (array_key_exists($key, $this->contextVariables)) {
            return $this->contextVariables[$key];
        }

        // Support dot notation for nested array access
        if (!str_contains($key, '.')) {
            return null;
        }

        $segments = explode('.', $key);
        $value = $this->contextVariables;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function set(string $key, mixed $value): void
    {
        $this->contextVariables[$key] = $value;
    }

    /**
     * Check if a context variable exists, supports dot notation for nested access.
     */
    public function has(string $key): bool
    {
        $value = $this->get($key);
        return $value !== null;
    }

    public function offsetExists($offset): bool
    {
        return $this->has($offset);
    }

    public function offsetGet($offset): mixed
    {
        return $this->get($offset);
    }

    public function offsetSet($offset, $value): void
    {
        $this->set($offset, $value);
    }

    public function offsetUnset($offset): void
    {
        unset($this->contextVariables[$offset]);
    }

    public function beforeRendering(): void {}

    public function afterRendering(string &$rendered): void {}
}
