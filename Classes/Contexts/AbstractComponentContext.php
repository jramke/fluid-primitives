<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

abstract class AbstractComponentContext implements ComponentContextInterface, \ArrayAccess
{
    private RenderingContextInterface $renderingContext;
    private RenderingContextInterface $parentRenderingContext;
    private array $contextVariables = [];

    /**
     * Initialize the context with state after dependency injection
     */
    public function initialize(
        RenderingContextInterface $renderingContext,
        RenderingContextInterface $parentRenderingContext,
        array $contextVariables = [],
    ): void {
        $this->renderingContext = $renderingContext;
        $this->parentRenderingContext = $parentRenderingContext;
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

    public function getAllVariables(): array
    {
        return $this->contextVariables;
    }

    public function get(string $key): mixed
    {
        return $this->contextVariables[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->contextVariables[$key] = $value;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->contextVariables) && $this->contextVariables[$key] !== null;
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
