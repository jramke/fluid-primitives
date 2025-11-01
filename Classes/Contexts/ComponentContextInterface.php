<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Psr\Container\ContainerInterface;

/**
 * Interface for the component contexts.
 */
interface ComponentContextInterface extends ContainerInterface
{
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
}
